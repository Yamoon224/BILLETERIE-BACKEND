<?php

namespace App\Domains\Scheduling\Contracts;

use App\Domains\Scheduling\DTOs\TripSearchCriteria;
use App\Models\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Depot complet des departs : lecture, ecriture, recherche voyageur.
 *
 * Etend la lecture etroite exposee aux autres domaines plutot que de la
 * dupliquer — une seule implementation sert les deux usages, et la
 * segregation reste lisible dans la signature des services qui consomment
 * l'un ou l'autre.
 */
interface TripRepositoryContract extends TripLookupContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Trip>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Recherche voyageur : departs correspondant a un couple de villes et une
     * date.
     *
     * @return list<Trip>
     */
    public function search(TripSearchCriteria $criteria): array;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Trip;

    /** @param  array<string, mixed>  $attributes */
    public function update(Trip $trip, array $attributes): Trip;

    public function delete(Trip $trip): void;

    /** Nombre de reservations non annulees rattachees a ce depart. */
    public function countActiveBookings(Trip $trip): int;
}
