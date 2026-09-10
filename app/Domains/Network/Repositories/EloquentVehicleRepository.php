<?php

namespace App\Domains\Network\Repositories;

use App\Domains\Network\Contracts\VehicleRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentVehicleRepository implements VehicleRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'registration' => 'registration',
        'model' => 'model',
        'class' => 'class',
        'capacity' => 'seat_capacity',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Vehicle> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Vehicle::query()
            ->with('company:id,code,name')
            ->withCount('trips')
            ->when($filters['company_id'] ?? null, fn ($query, $id) => $query->where('company_id', $id))
            ->when($filters['class'] ?? null, fn ($query, $class) => $query->where('class', $class))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('registration', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%"),
            ))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'registration', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Vehicle
    {
        return Vehicle::query()->with('company')->withCount('trips')->findOrFail($id);
    }

    public function create(array $attributes): Vehicle
    {
        return Vehicle::create($attributes);
    }

    public function update(Vehicle $vehicle, array $attributes): Vehicle
    {
        $vehicle->update($attributes);

        return $vehicle->refresh();
    }

    public function delete(Vehicle $vehicle): void
    {
        $vehicle->delete();
    }

    public function countDependents(Vehicle $vehicle): int
    {
        return $vehicle->trips()->count();
    }
}
