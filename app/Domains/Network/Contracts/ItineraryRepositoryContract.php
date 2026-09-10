<?php

namespace App\Domains\Network\Contracts;

use App\Models\Itinerary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ItineraryRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Itinerary>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Itinerary;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Itinerary;

    /** @param  array<string, mixed>  $attributes */
    public function update(Itinerary $itinerary, array $attributes): Itinerary;

    public function delete(Itinerary $itinerary): void;

    /** Nombre de departs deja programmes sur cet itineraire. */
    public function countDependents(Itinerary $itinerary): int;
}
