<?php

namespace App\Domains\CarRental\Contracts;

use App\Models\RentalVehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RentalVehicleRepositoryContract
{
    /**
     * Liste d'administration : tous statuts, bornee par partenaire cote
     * appelant.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RentalVehicle>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Recherche publique : uniquement les fiches actives.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RentalVehicle>
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): RentalVehicle;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): RentalVehicle;

    /** @param  array<string, mixed>  $attributes */
    public function update(RentalVehicle $rentalVehicle, array $attributes): RentalVehicle;

    public function delete(RentalVehicle $rentalVehicle): void;
}
