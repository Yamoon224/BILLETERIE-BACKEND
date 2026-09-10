<?php

namespace App\Domains\Network\Services;

use App\Domains\Network\Contracts\StationRepositoryContract;
use App\Domains\Network\Exceptions\ResourceInUseException;
use App\Models\Station;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Gares routieres et points d'embarquement. */
final class StationService
{
    public function __construct(private readonly StationRepositoryContract $stations) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Station>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->stations->paginate($filters, $perPage);
    }

    public function find(string $id): Station
    {
        return $this->stations->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Station
    {
        return $this->stations->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Station $station, array $data): Station
    {
        return $this->stations->update($station, $data);
    }

    /** @throws ResourceInUseException */
    public function delete(Station $station): void
    {
        $dependents = $this->stations->countDependents($station);

        if ($dependents > 0) {
            throw ResourceInUseException::make('La gare', $station->name, $dependents);
        }

        $this->stations->delete($station);
    }
}
