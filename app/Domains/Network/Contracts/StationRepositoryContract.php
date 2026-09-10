<?php

namespace App\Domains\Network\Contracts;

use App\Models\Station;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StationRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Station>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Station;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Station;

    /** @param  array<string, mixed>  $attributes */
    public function update(Station $station, array $attributes): Station;

    public function delete(Station $station): void;

    public function countDependents(Station $station): int;
}
