<?php

namespace App\Domains\CarRental\Services;

use App\Domains\CarRental\Contracts\RentalVehicleRepositoryContract;
use App\Models\RentalVehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Catalogue de vehicules de location courte duree.
 *
 * Comme pour les appartements, aucune reservation en ligne ne s'appuie encore
 * sur ce catalogue : une fiche se modifie librement, sans regle de gel a
 * surveiller ici.
 */
final class RentalVehicleService
{
    public function __construct(private readonly RentalVehicleRepositoryContract $vehicles) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RentalVehicle>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->vehicles->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RentalVehicle>
     */
    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->vehicles->search($filters, $perPage);
    }

    public function find(string $id): RentalVehicle
    {
        return $this->vehicles->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): RentalVehicle
    {
        return $this->vehicles->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(RentalVehicle $rentalVehicle, array $data): RentalVehicle
    {
        return $this->vehicles->update($rentalVehicle, $data);
    }

    public function delete(RentalVehicle $rentalVehicle): void
    {
        $this->vehicles->delete($rentalVehicle);
    }
}
