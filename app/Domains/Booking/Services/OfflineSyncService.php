<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Contracts\BookingRepositoryContract;
use App\Domains\Booking\DTOs\BookingDraft;
use App\Domains\Booking\DTOs\PassengerDraft;
use App\Domains\Booking\Enums\BookingChannel;
use App\Domains\Booking\Exceptions\OfflineSaleRejectedException;
use App\Domains\Payments\Enums\PaymentMethod;
use App\Domains\Shared\Exceptions\DomainException;
use Illuminate\Support\Carbon;

/**
 * Reconciliation des ventes realisees hors ligne au guichet.
 *
 * Le cahier des charges exige que l'agent puisse vendre sans reseau et que la
 * synchronisation differee soit correcte. « Correcte » signifie ici trois
 * choses precises, et chacune correspond a une decision de ce service.
 *
 * **1. Rien n'est perdu, rien n'est double.** Chaque vente porte une reference
 * attribuee par la tablette avant l'envoi. Une vente deja connue sous cette
 * reference est renvoyee telle quelle, sans etre recreee : une reponse perdue
 * sur un reseau intermittent ne vend pas deux fois la meme place.
 *
 * **2. Un lot n'est pas atomique.** Une tablette remonte vingt ventes ; si la
 * troisieme porte sur une place entre-temps vendue en ligne, les dix-neuf
 * autres doivent quand meme entrer. Refuser le lot entier ferait perdre des
 * ventes reelles a cause d'un seul conflit — c'est pourquoi chaque vente est
 * traitee dans sa propre transaction et rend son propre verdict.
 *
 * **3. Un refus est rendu, jamais avale.** Une vente au guichet a deja eu lieu
 * dans le monde reel : de l'argent a change de main, un ticket est imprime, un
 * voyageur est peut-etre assis. Un refus silencieux creerait un ecart de
 * caisse invisible jusqu'au decompte du soir. Chaque refus revient donc a la
 * tablette avec son motif, a charge d'un responsable de le traiter.
 */
final class OfflineSyncService
{
    public function __construct(
        private readonly BookingRepositoryContract $bookings,
        private readonly CounterSaleService $counter,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $sales
     * @param  string|null  $agentUserId  agent authentifie qui remonte le lot
     * @return list<array<string, mixed>> un verdict par vente, dans l'ordre recu
     */
    public function synchronise(array $sales, ?string $agentUserId, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        return array_map(fn (array $sale) => $this->synchroniseOne($sale, $agentUserId, $now), $sales);
    }

    /** @param  array<string, mixed>  $sale
     * @return array<string, mixed>
     */
    private function synchroniseOne(array $sale, ?string $agentUserId, Carbon $now): array
    {
        $clientReference = (string) $sale['client_reference'];

        try {
            $existing = $this->bookings->findByClientReference($clientReference);

            if ($existing !== null) {
                return [
                    'client_reference' => $clientReference,
                    'status' => 'duplicate',
                    'booking_reference' => $existing->reference,
                    'booking_id' => $existing->id,
                ];
            }

            $soldAt = Carbon::parse((string) $sale['sold_at']);
            $this->guardSaleAge($clientReference, $soldAt, $now);

            $result = $this->counter->sell(
                draft: $this->draftFrom($sale, $clientReference, $agentUserId, $soldAt),
                method: PaymentMethod::from((string) ($sale['payment_method'] ?? 'cash')),
                agentUserId: $agentUserId,
                payerMsisdn: $sale['payer_msisdn'] ?? null,
                paymentClientReference: $clientReference.'-PAY',
                now: $now,
            );

            return [
                'client_reference' => $clientReference,
                'status' => 'accepted',
                'booking_reference' => $result['booking']->reference,
                'booking_id' => $result['booking']->id,
            ];
        } catch (DomainException $exception) {
            // Le refus est rendu avec son code applicatif : la tablette peut
            // ainsi distinguer « place deja vendue » — a traiter par un
            // remboursement — de « depart annule » — a traiter par un report.
            return [
                'client_reference' => $clientReference,
                'status' => 'rejected',
                'error_code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'context' => $exception->context(),
            ];
        }
    }

    /** @param  array<string, mixed>  $sale */
    private function draftFrom(array $sale, string $clientReference, ?string $agentUserId, Carbon $soldAt): BookingDraft
    {
        /** @var list<array{seat_number: string, name: string, phone?: string|null}> $passengers */
        $passengers = $sale['passengers'];

        return new BookingDraft(
            tripId: (string) $sale['trip_id'],
            channel: BookingChannel::OfflineCounter,
            customerName: (string) $sale['customer_name'],
            customerPhone: (string) $sale['customer_phone'],
            customerEmail: $sale['customer_email'] ?? null,
            passengers: array_map(PassengerDraft::fromArray(...), $passengers),
            soldByUserId: $agentUserId,
            stationId: $sale['station_id'] ?? null,
            clientReference: $clientReference,
            soldOfflineAt: $soldAt,
            notes: $sale['notes'] ?? null,
        );
    }

    /**
     * Une vente trop ancienne, ou datee dans le futur, n'est pas enregistree
     * automatiquement.
     *
     * Le futur trahit une horloge de tablette deréglée — cas courant sur du
     * materiel bon marche dont la pile de sauvegarde est morte —, et une vente
     * datee de demain fausserait la recette du jour. Une vente vieille de
     * plusieurs jours, elle, arrive apres le depart du bus : ce n'est plus une
     * vente a enregistrer mais un incident a arbitrer.
     */
    private function guardSaleAge(string $clientReference, Carbon $soldAt, Carbon $now): void
    {
        // Quelques minutes de tolerance : les horloges de tablettes derivent,
        // et refuser une vente pour trente secondes d'avance serait absurde.
        if ($soldAt->greaterThan($now->copy()->addMinutes(5))) {
            throw OfflineSaleRejectedException::inFuture($clientReference);
        }

        $maxAgeHours = (int) config('ticketing.offline_sync_max_age_hours');

        if ($soldAt->lessThan($now->copy()->subHours($maxAgeHours))) {
            throw OfflineSaleRejectedException::tooOld($clientReference, $maxAgeHours);
        }
    }
}
