<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\DTOs\BookingDraft;
use App\Domains\Notifications\Services\TicketNotifier;
use App\Domains\Payments\Enums\MobileMoneyProvider;
use App\Domains\Payments\Enums\PaymentMethod;
use App\Domains\Payments\Services\PaymentService;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Vente au guichet.
 *
 * Le guichet inverse l'ordre de la vente en ligne : l'agent encaisse d'abord,
 * enregistre ensuite. La reservation nait donc confirmee, sans echeance de
 * paiement — il n'y a rien a attendre, l'argent est dans la caisse.
 *
 * Ce service existe parce que cette sequence appartient au guichet et a lui
 * seul. La disperser entre `BookingService` et `PaymentService` obligerait
 * chacun a connaitre le canal de vente de l'autre, alors qu'aucun des deux n'a
 * besoin de le savoir.
 *
 * Le SMS part ici et non dans `PaymentService` : un encaissement en especes ne
 * declenche aucun rappel d'agregateur, donc aucun des chemins qui, en ligne,
 * finissent par notifier le voyageur.
 */
final class CounterSaleService
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PaymentService $payments,
        private readonly TicketNotifier $notifier,
    ) {}

    /**
     * @return array{booking: Booking, payment: Payment}
     */
    public function sell(
        BookingDraft $draft,
        PaymentMethod $method,
        ?string $agentUserId,
        ?MobileMoneyProvider $provider = null,
        ?string $payerMsisdn = null,
        ?string $paymentClientReference = null,
        ?Carbon $now = null,
    ): array {
        $now ??= Carbon::now();

        $booking = $this->bookings->reserve($draft, $now);

        $payment = $method === PaymentMethod::Cash
            ? $this->payments->collectCash($booking, $agentUserId, $paymentClientReference, $now)
            : $this->payments->payWithMobileMoney(
                $booking,
                $provider ?? MobileMoneyProvider::OrangeMoney,
                (string) ($payerMsisdn ?? $booking->customer_phone),
                $now,
            );

        // Le SMS n'est envoye que pour les especes : le chemin mobile money
        // notifie deja depuis PaymentService, et deux SMS pour un billet
        // coutent deux fois et font douter le voyageur d'avoir paye deux fois.
        if ($method === PaymentMethod::Cash) {
            $this->notifier->sendBookingConfirmation($booking->refresh());
        }

        return ['booking' => $booking->refresh(), 'payment' => $payment];
    }
}
