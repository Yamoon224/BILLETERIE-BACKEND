<?php

namespace App\Domains\Network\Contracts;

use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VehicleRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Vehicle;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Vehicle;

    /** @param  array<string, mixed>  $attributes */
    public function update(Vehicle $vehicle, array $attributes): Vehicle;

    public function delete(Vehicle $vehicle): void;

    /** Nombre de departs deja programmes sur ce vehicule. */
    public function countDependents(Vehicle $vehicle): int;
}
