<?php

namespace App\Domains\Booking\DTOs;

use App\Domains\Booking\Enums\BookingChannel;
use Illuminate\Support\Carbon;

/**
 * Demande de reservation, avant toute ecriture.
 *
 * Immutable, et volontairement sans identifiant de compagnie ni montant : les
 * deux se deduisent du depart, et les laisser entrer par la requete
 * permettrait a un client de vendre a son propre tarif. Le seul montant
 * pouvant venir de l'exterieur serait une reduction, qui n'existe pas dans ce
 * lot.
 */
final readonly class BookingDraft
{
    /** @param  list<PassengerDraft>  $passengers */
    public function __construct(
        public string $tripId,
        public BookingChannel $channel,
        public string $customerName,
        public string $customerPhone,
        public ?string $customerEmail,
        public array $passengers,
        public ?string $customerUserId = null,
        public ?string $soldByUserId = null,
        public ?string $stationId = null,
        /** Le voyageur choisit l'option ; son cout se deduit de la configuration, jamais de la requete. */
        public bool $wantsRefundGuarantee = false,
        /** Identifiant du geste cote tablette : rend la vente idempotente. */
        public ?string $clientReference = null,
        /** Heure reelle de la vente au guichet, si elle precede la synchronisation. */
        public ?Carbon $soldOfflineAt = null,
        public ?string $notes = null,
    ) {}

    /** @return list<string> */
    public function seatNumbers(): array
    {
        return array_map(static fn (PassengerDraft $passenger) => $passenger->seatNumber, $this->passengers);
    }

    public function seatsCount(): int
    {
        return count($this->passengers);
    }

    /**
     * @return list<array{seat_number: string, passenger_name: string, passenger_phone: string|null, passenger_id_number: string|null}>
     */
    public function passengersForIssuance(): array
    {
        return array_map(static fn (PassengerDraft $passenger) => [
            'seat_number' => $passenger->seatNumber,
            'passenger_name' => $passenger->name,
            'passenger_phone' => $passenger->phone,
            'passenger_id_number' => $passenger->idNumber,
        ], $this->passengers);
    }
}
