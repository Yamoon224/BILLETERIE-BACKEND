<?php

namespace App\Domains\Scheduling\Services;

use App\Domains\Scheduling\Contracts\TripRepositoryContract;
use App\Domains\Scheduling\Enums\TripStatus;
use App\Domains\Scheduling\Exceptions\TripNotEditableException;
use App\Domains\Shared\Support\Reference;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Programmation des departs par le gestionnaire de compagnie.
 *
 * La regle centrale de ce service tient en une phrase : **un depart fige, a sa
 * creation, ce qu'il vend**. Le tarif de l'itineraire et le plan de salle du
 * vehicule sont recopies dans le depart, et plus jamais relus depuis leur
 * source.
 *
 * Sans cette copie, augmenter le tarif d'une liaison changerait
 * retroactivement le prix des billets deja vendus, et remplacer un car de
 * 70 places par un minibus de 30 renumeroterait des places deja attribuees a
 * des voyageurs — qui se presenteraient avec un billet « place 52 » devant un
 * vehicule qui s'arrete a 30.
 */
final class TripService
{
    public function __construct(private readonly TripRepositoryContract $trips) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Trip>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->trips->paginate($filters, $perPage);
    }

    public function find(string $id): Trip
    {
        return $this->trips->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Itinerary $itinerary, Vehicle $vehicle): Trip
    {
        $departsAt = Carbon::parse((string) $data['departs_at']);

        return $this->trips->create([
            'reference' => Reference::dated('DEP', $departsAt),
            'company_id' => $itinerary->company_id,
            'itinerary_id' => $itinerary->id,
            'vehicle_id' => $vehicle->id,
            'departure_station_id' => $data['departure_station_id'],
            'arrival_station_id' => $data['arrival_station_id'],
            'departs_at' => $departsAt,
            // L'heure d'arrivee se deduit de la duree de l'itineraire quand
            // elle n'est pas donnee : un gestionnaire qui programme trente
            // departs ne doit pas la ressaisir trente fois.
            'arrives_at' => isset($data['arrives_at'])
                ? Carbon::parse((string) $data['arrives_at'])
                : $departsAt->copy()->addMinutes($itinerary->duration_minutes),
            // --- Les trois copies figees ---------------------------------
            'price' => $data['price'] ?? $itinerary->base_price,
            'seat_capacity' => $vehicle->seat_capacity,
            'seats_per_row' => $vehicle->seats_per_row,
            'status' => TripStatus::Scheduled,
        ]);
    }

    /**
     * Modifie un depart programme.
     *
     * Ni le tarif ni la capacite ne sont modifiables une fois des billets
     * vendus : ce sont les deux valeurs que le voyageur a lues avant de payer.
     * L'horaire, lui, reste modifiable — un retard annonce vaut mieux qu'un
     * horaire faux — et c'est justement pour cela que le SMS de confirmation
     * porte la reference du depart et non seulement son heure.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Trip $trip, array $data): Trip
    {
        if ($trip->status->isFinal()) {
            throw TripNotEditableException::forStatus($trip->reference, $trip->status);
        }

        $sold = $this->trips->countActiveBookings($trip);

        if ($sold > 0) {
            foreach (['price', 'vehicle_id'] as $frozen) {
                if (array_key_exists($frozen, $data)) {
                    throw TripNotEditableException::alreadySold($trip->reference, $sold, $frozen);
                }
            }
        }

        return $this->trips->update($trip, $data);
    }

    /**
     * Annule un depart.
     *
     * L'annulation ne touche pas aux reservations : elle rend le depart
     * invendable et non embarquable, mais le remboursement des voyageurs deja
     * payants est une decision commerciale qui appartient a la compagnie, pas
     * un effet de bord d'un changement de statut. Les reservations concernees
     * restent donc visibles et annulables une par une, avec leur motif.
     */
    public function cancel(Trip $trip, string $reason, ?Carbon $now = null): Trip
    {
        if ($trip->status->isFinal()) {
            throw TripNotEditableException::forStatus($trip->reference, $trip->status);
        }

        return $this->trips->update($trip, [
            'status' => TripStatus::Cancelled,
            'cancellation_reason' => $reason,
            'cancelled_at' => $now ?? Carbon::now(),
        ]);
    }

    /** Fait avancer un depart dans son cycle de vie (embarquement, depart, arrivee). */
    public function changeStatus(Trip $trip, TripStatus $status): Trip
    {
        if ($trip->status->isFinal()) {
            throw TripNotEditableException::forStatus($trip->reference, $trip->status);
        }

        return $this->trips->update($trip, ['status' => $status]);
    }

    /**
     * Supprime un depart.
     *
     * Refuse des qu'une reservation existe, meme annulee : la reference du
     * depart figure sur des billets imprimes et dans des SMS deja partis, et
     * un support client qui ne retrouve pas un depart cite par un voyageur
     * n'a rien a lui repondre.
     */
    public function delete(Trip $trip): void
    {
        $bookings = $trip->bookings()->count();

        if ($bookings > 0) {
            throw TripNotEditableException::hasBookings($trip->reference, $bookings);
        }

        $this->trips->delete($trip);
    }
}
