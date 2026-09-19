<?php

namespace App\Domains\RouteGrid\Contracts;

use App\Models\RouteGridEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RouteGridRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RouteGridEntry>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Lignes actives d'une liaison, de la moins chere a la plus chere.
     *
     * @return list<RouteGridEntry>
     */
    public function activeForRoute(string $originSlug, string $destinationSlug): array;

    public function findOrFail(string $id): RouteGridEntry;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): RouteGridEntry;

    /** @param  array<string, mixed>  $attributes */
    public function update(RouteGridEntry $entry, array $attributes): RouteGridEntry;

    public function delete(RouteGridEntry $entry): void;
}
