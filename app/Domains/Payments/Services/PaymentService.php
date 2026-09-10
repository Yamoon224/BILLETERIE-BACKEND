<?php

namespace App\Domains\Payments\Services;

use App\Domains\Booking\Exceptions\BookingNotPayableException;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Notifications\Services\TicketNotifier;
use App\Domains\Payments\Contracts\PaymentGatewayContract;
use App\Domains\Payments\Contracts\PaymentRepositoryContract;
use App\Domains\Payments\DTOs\GatewayCallback;
use App\Domains\Payments\DTOs\PaymentIntent;
use App\Domains\Payments\Enums\MobileMoneyProvider;
use App\Domains\Payments\Enums\PaymentMethod;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Payments\Exceptions\UnknownPaymentReferenceException;
use App\Domains\Shared\Support\Reference;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Encaissements et leurs consequences sur la reservation.
 *
 * Ce service est le seul a decider qu'une vente est payee. Il en tire ensuite
 * la consequence en demandant a `BookingService` de confirmer — il ne modifie
 * jamais lui-meme le statut d'une reservation. La frontiere n'est pas
 * decorative : si les deux domaines pouvaient ecrire le statut, les regles
 * d'annulation et de confirmation existeraient en deux exemplaires et
 * divergeraient au premier correctif applique d'un seul cote.
 *
 * L'expiration du blocage est verifiee **avant** d'appeler l'agregateur.
 * Encaisser d'abord et decouvrir ensuite que les places sont reparties a la
 * vente produirait un remboursement, un voyageur sans siege et une place
 * vendue deux fois — dans cet ordre.
 */
final class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryContract $payments,
        private readonly PaymentGatewayContract $gateway,
        private readonly BookingService $bookings,
        private readonly TicketNotifier $notifier,
    ) {}

    /**
     * Lance un encaissement mobile money pour une reservation en attente.
     */
    public function payWithMobileMoney(
        Booking $booking,
        MobileMoneyProvider $provider,
        string $payerMsisdn,
        ?Carbon $now = null,
    ): Payment {
        $now ??= Carbon::now();

        $this->guardPayable($booking, $now);

        $payment = $this->payments->create([
            'reference' => Reference::make('PAY'),
            'booking_id' => $booking->id,
            'method' => PaymentMethod::MobileMoney,
            'provider' => $provider,
            'gateway' => $this->gateway->name(),
            'amount' => $booking->total_amount,
            'currency' => $booking->currency,
            'status' => PaymentStatus::Pending,
            'payer_msisdn' => $payerMsisdn,
        ]);

        $initiation = $this->gateway->initiate(new PaymentIntent(
            paymentReference: $payment->reference,
            bookingReference: $booking->reference,
            amount: $booking->total_amount,
            currency: $booking->currency,
            provider: $provider,
            payerMsisdn: $payerMsisdn,
            description: 'Billet '.$booking->reference,
        ));

        $payment = $this->payments->update($payment, [
            'external_reference' => $initiation->externalReference,
            'status' => $initiation->status,
            'failure_reason' => $initiation->failureReason,
            'gateway_payload' => $initiation->rawPayload,
            'authorized_at' => $initiation->status === PaymentStatus::Succeeded ? $now : null,
            'paid_at' => $initiation->status === PaymentStatus::Succeeded ? $now : null,
            'failed_at' => $initiation->status === PaymentStatus::Failed ? $now : null,
        ]);

        if ($payment->status === PaymentStatus::Succeeded) {
            $this->settle($booking, $now);
        }

        return $payment;
    }

    /**
     * Encaissement en especes au guichet.
     *
     * Ne passe par aucun agregateur : l'agent constate la remise d'argent. Le
     * paiement nait donc directement acquis, et c'est l'identite de l'agent qui
     * en tient lieu de justificatif.
     */
    public function collectCash(
        Booking $booking,
        ?string $collectedByUserId,
        ?string $clientReference = null,
        ?Carbon $now = null,
    ): Payment {
        $now ??= Carbon::now();

        // Idempotence de la remontee hors ligne : un encaissement rejoue ne
        // doit pas doubler la recette de la journee.
        if ($clientReference !== null) {
            $existing = $this->payments->findByClientReference($clientReference);

            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->payments->create([
            'reference' => Reference::make('PAY'),
            'booking_id' => $booking->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'counter',
            'amount' => $booking->total_amount,
            'currency' => $booking->currency,
            'status' => PaymentStatus::Succeeded,
            'collected_by_user_id' => $collectedByUserId,
            'authorized_at' => $now,
            'paid_at' => $now,
            'client_reference' => $clientReference,
        ]);
    }

    /**
     * Traite un rappel de l'agregateur.
     *
     * Verrouille l'encaissement : les agregateurs rejouent volontiers leurs
     * rappels, parfois en parallele. Sans verrou, deux rappels simultanes
     * confirmeraient deux fois la meme vente et enverraient deux SMS pour un
     * seul billet.
     */
    public function handleCallback(GatewayCallback $callback, ?Carbon $now = null): Payment
    {
        $now ??= Carbon::now();

        $payment = DB::transaction(function () use ($callback, $now): Payment {
            $payment = $this->payments->lockByExternalReferenceForUpdate($callback->externalReference);

            if ($payment === null) {
                throw UnknownPaymentReferenceException::make($callback->externalReference);
            }

            // Un rappel sur un encaissement deja tranche est ignore : le
            // premier verdict fait foi, et le rejouer reecrirait une date
            // d'encaissement deja comptabilisee.
            if (! $payment->status->isOpen()) {
                return $payment;
            }

            return $this->payments->update($payment, [
                'status' => $callback->status,
                'failure_reason' => $callback->failureReason,
                'payer_msisdn' => $callback->payerMsisdn ?? $payment->payer_msisdn,
                'gateway_payload' => $callback->rawPayload,
                'paid_at' => $callback->status === PaymentStatus::Succeeded ? $now : null,
                'failed_at' => $callback->status === PaymentStatus::Failed ? $now : null,
            ]);
        });

        // La confirmation et la notification vivent hors de la transaction :
        // la premiere ouvre la sienne, la seconde parle a un operateur externe
        // qu'on ne veut pas voir tenir un verrou de base ouvert.
        if ($payment->status === PaymentStatus::Succeeded && $payment->wasChanged('status')) {
            $this->settle($payment->booking, $now);
        }

        return $payment;
    }

    /** Rembourse un encaissement acquis. */
    public function refund(Payment $payment, ?Carbon $now = null): Payment
    {
        if (! $payment->status->isSettled()) {
            return $payment;
        }

        return $this->payments->update($payment, [
            'status' => PaymentStatus::Refunded,
            'refunded_at' => $now ?? Carbon::now(),
        ]);
    }

    /**
     * Consequences d'un encaissement acquis : la reservation est confirmee, le
     * billet part au voyageur.
     */
    private function settle(Booking $booking, Carbon $now): void
    {
        $this->bookings->confirm($booking, $now);
        $this->notifier->sendBookingConfirmation($booking->refresh());
    }

    private function guardPayable(Booking $booking, Carbon $now): void
    {
        if ($booking->isHoldExpired($now)) {
            throw BookingNotPayableException::holdExpired($booking->reference);
        }

        if (! $booking->status->isPayable()) {
            throw BookingNotPayableException::forStatus($booking->reference, $booking->status);
        }
    }
}
