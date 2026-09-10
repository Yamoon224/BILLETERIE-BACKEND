<?php

namespace App\Domains\Network\Services;

use App\Domains\Network\Contracts\VehicleRepositoryContract;
use App\Domains\Network\Exceptions\ResourceInUseException;
use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Parc de vehicules d'une compagnie.
 *
 * Capacite et plan de salle sont modifiables librement : ils ne sont lus qu'a
 * la creation d'un depart, qui en garde une copie figee. Reduire la capacite
 * d'un car ne peut donc pas invalider des places deja vendues sur des departs
 * programmes — c'est precisement la raison d'etre de cette copie.
 */
final class VehicleService
{
    public function __construct(private readonly VehicleRepositoryContract $vehicles) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->vehicles->paginate($filters, $perPage);
    }

    public function find(string $id): Vehicle
    {
        return $this->vehicles->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Vehicle
    {
        return $this->vehicles->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        return $this->vehicles->update($vehicle, $data);
    }

    /** @throws ResourceInUseException */
    public function delete(Vehicle $vehicle): void
    {
        $dependents = $this->vehicles->countDependents($vehicle);

        if ($dependents > 0) {
            throw ResourceInUseException::make('Le vehicule', $vehicle->registration, $dependents);
        }

        $this->vehicles->delete($vehicle);
    }
}
