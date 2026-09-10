<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Contracts\BookingRepositoryContract;
use App\Domains\Booking\DTOs\BookingDraft;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Exceptions\BookingNotCancellableException;
use App\Domains\Booking\Exceptions\InvalidSeatException;
use App\Domains\Booking\Exceptions\SeatUnavailableException;
use App\Domains\Booking\Exceptions\TripNotBookableException;
use App\Domains\Scheduling\Contracts\TripLookupContract;
use App\Domains\Shared\Support\Money;
use App\Domains\Shared\Support\Reference;
use App\Domains\Ticketing\Contracts\OccupiedSeatReaderContract;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Vente : reservation, confirmation, annulation, expiration.
 *
 * Ce service porte la garantie « une place n'est jamais vendue deux fois », et
 * il la porte a **deux niveaux**, volontairement redondants :
 *
 *  - une verification prealable des places prises, qui produit un message
 *    utile (« les places 12A et 12B ne sont plus disponibles ») ;
 *  - l'index unique `(trip_id, seat_number)` en base, qui produit la garantie.
 *
 * Le premier niveau ne garantit rien : entre la lecture et l'insertion, une
 * autre vente peut passer. C'est pour cela que la violation d'index est
 * rattrapee et retraduite ici — elle n'est pas un incident technique, c'est le
 * cas nominal d'une course perdue, et le client doit recevoir la meme reponse
 * que s'il avait ete devance d'une seconde plus tot.
 *
 * L'inverse — se contenter de l'index — donnerait un message inutilisable :
 * l'erreur SQL ne dit pas quelles places manquent, seulement qu'une contrainte
 * a saute.
 */
final class BookingService
{
    public function __construct(
        private readonly BookingRepositoryContract $bookings,
        private readonly TripLookupContract $trips,
        private readonly OccupiedSeatReaderContract $occupiedSeats,
        private readonly TicketIssuanceService $tickets,
    ) {}

    /**
     * Enregistre une reservation et emet ses billets.
     *
     * Une vente en ligne nait `pending` avec une echeance de paiement ; une
     * vente au guichet nait `confirmed`, l'argent ayant deja change de main.
     */
    public function reserve(BookingDraft $draft, ?Carbon $now = null): Booking
    {
        $now ??= Carbon::now();

        // Idempotence : une tablette qui rejoue son envoi retrouve sa vente.
        if ($draft->clientReference !== null) {
            $existing = $this->bookings->findByClientReference($draft->clientReference);

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($draft, $now): Booking {
            // Verrou sur le depart : il serialise les ventes concurrentes pour
            // que la seconde voie l'etat reel plutot que d'echouer a l'aveugle.
            $trip = $this->trips->lockForUpdate($draft->tripId) ?? $this->trips->findOrFail($draft->tripId);
            $trip->loadMissing('company');

            $this->guardTripAcceptsBooking($trip, $now);
            $this->guardSeatsExist($trip, $draft->seatNumbers());
            $this->guardSeatsAvailable($trip, $draft->seatNumbers());

            $booking = $this->bookings->create($this->attributesFor($draft, $trip, $now));

            try {
                $this->tickets->issueForBooking($booking, $trip, $draft->passengersForIssuance());
            } catch (UniqueConstraintViolationException) {
                // Course perdue entre la verification et l'insertion. La levee
                // annule la transaction : aucune reservation orpheline ne
                // subsiste, et le client recoit la meme reponse que s'il avait
                // ete devance une seconde plus tot.
                throw SeatUnavailableException::forSeats($draft->seatNumbers());
            }

            return $booking->refresh();
        });
    }

    /**
     * Confirme une reservation dont le paiement est acquis.
     *
     * Ne verifie pas le paiement lui-meme : c'est le domaine Payments qui en
     * decide, et lui seul. Ce service se contente d'en tirer les consequences
     * sur la reservation — sans quoi deux domaines connaitraient les regles
     * d'encaissement, et elles finiraient par diverger.
     */
    public function confirm(Booking $booking, ?Carbon $now = null): Booking
    {
        if ($booking->status === BookingStatus::Confirmed) {
            return $booking;
        }

        return $this->bookings->update($booking, [
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => $now ?? Carbon::now(),
            // L'echeance disparait : une reservation payee ne peut plus
            // expirer, et laisser la date en base l'exposerait a une commande
            // d'expiration mal filtree.
            'expires_at' => null,
        ]);
    }

    /**
     * Annule une reservation et rend ses places a la vente.
     *
     * Refuse si un billet a deja ete scanne : le voyageur est monte, la place
     * est consommee, et la remettre en vente pour un bus qui roule creerait
     * exactement le probleme que la billetterie doit empecher.
     */
    public function cancel(
        Booking $booking,
        ?string $reason = null,
        BookingStatus $target = BookingStatus::Cancelled,
        ?Carbon $now = null,
    ): Booking {
        if (! $booking->status->holdsSeats()) {
            throw BookingNotCancellableException::forStatus($booking->reference, $booking->status);
        }

        return DB::transaction(function () use ($booking, $reason, $target, $now): Booking {
            $ticketStatus = $target === BookingStatus::Refunded
                ? TicketStatus::Refunded
                : TicketStatus::Cancelled;

            foreach ($booking->tickets()->get() as $ticket) {
                if ($ticket->isScanned()) {
                    throw BookingNotCancellableException::alreadyBoarded($booking->reference);
                }

                $this->tickets->cancel($ticket, $ticketStatus);
            }

            return $this->bookings->update($booking, [
                'status' => $target,
                'cancelled_at' => $now ?? Carbon::now(),
                'cancellation_reason' => $reason,
            ]);
        });
    }

    /**
     * Fait expirer un blocage de places non paye.
     *
     * Distinct de `cancel` : une reservation expiree n'a jamais ete payee,
     * elle ne genere donc ni remboursement ni ligne de recette negative. Les
     * confondre fausserait la recette autant que le taux de remplissage.
     */
    public function expire(Booking $booking, ?Carbon $now = null): Booking
    {
        return DB::transaction(function () use ($booking, $now): Booking {
            foreach ($booking->tickets()->get() as $ticket) {
                $this->tickets->cancel($ticket, TicketStatus::Cancelled);
            }

            return $this->bookings->update($booking, [
                'status' => BookingStatus::Expired,
                'cancelled_at' => $now ?? Carbon::now(),
                'cancellation_reason' => 'Delai de paiement ecoule.',
            ]);
        });
    }

    // --- Garde-fous ---------------------------------------------------------

    private function guardTripAcceptsBooking(Trip $trip, Carbon $now): void
    {
        if (! $trip->status->acceptsBookings()) {
            throw TripNotBookableException::forStatus($trip->reference, $trip->status);
        }

        // Un depart dont l'heure est passee reste vendable tant qu'il est en
        // embarquement — le bus est encore a quai, et le guichet ecoule ses
        // dernieres places devant la porte. Au-dela, non.
        if ($trip->hasLeft($now) && ! $trip->isWithinBoardingWindow($now)) {
            throw TripNotBookableException::alreadyDeparted($trip->reference);
        }
    }

    /** @param  list<string>  $seats */
    private function guardSeatsExist(Trip $trip, array $seats): void
    {
        $seen = [];

        foreach ($seats as $seat) {
            if (isset($seen[$seat])) {
                throw InvalidSeatException::duplicated($seat);
            }

            $seen[$seat] = true;
        }

        $map = $trip->seatMap();
        $unknown = array_values(array_filter($seats, static fn (string $seat) => ! $map->has($seat)));

        if ($unknown !== []) {
            throw InvalidSeatException::unknown($unknown);
        }
    }

    /** @param  list<string>  $seats */
    private function guardSeatsAvailable(Trip $trip, array $seats): void
    {
        $taken = array_intersect($seats, $this->occupiedSeats->occupiedSeatNumbers($trip->id));

        if ($taken !== []) {
            throw SeatUnavailableException::forSeats(array_values($taken));
        }
    }

    /** @return array<string, mixed> */
    private function attributesFor(BookingDraft $draft, Trip $trip, Carbon $now): array
    {
        $seats = $draft->seatsCount();
        $total = $trip->price * $seats;
        $perMille = $trip->company->effectiveCommissionPerMille();
        $holdsSeats = $draft->channel->requiresSeatHold();

        return [
            'reference' => Reference::make('RES', (int) config('ticketing.reference_random_length', 6)),
            'trip_id' => $trip->id,
            'company_id' => $trip->company_id,
            'channel' => $draft->channel,
            'status' => $holdsSeats ? BookingStatus::Pending : BookingStatus::Confirmed,
            'customer_name' => $draft->customerName,
            'customer_phone' => $draft->customerPhone,
            'customer_email' => $draft->customerEmail,
            'customer_user_id' => $draft->customerUserId,
            'sold_by_user_id' => $draft->soldByUserId,
            'station_id' => $draft->stationId ?? $trip->departure_station_id,
            'seats_count' => $seats,
            'total_amount' => $total,
            'currency' => (string) config('ticketing.currency'),
            // La commission est figee ici, taux compris : une revision de
            // bareme ne doit pas reecrire ce qui a deja ete facture.
            'commission_amount' => Money::commission($total, $perMille),
            'commission_per_mille' => $perMille,
            'expires_at' => $holdsSeats
                ? $now->copy()->addMinutes((int) config('ticketing.hold_minutes'))
                : null,
            'confirmed_at' => $holdsSeats ? null : $now,
            'client_reference' => $draft->clientReference,
            'sold_offline_at' => $draft->soldOfflineAt,
            'synced_at' => $draft->soldOfflineAt !== null ? $now : null,
            'notes' => $draft->notes,
        ];
    }
}
