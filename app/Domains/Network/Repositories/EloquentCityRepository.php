<?php

namespace App\Domains\Network\Repositories;

use App\Domains\Network\Contracts\CityRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\City;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentCityRepository implements CityRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'name' => 'name',
        'region' => 'region',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, City> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return City::query()
            ->withCount('stations')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%"),
            ))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'name', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return list<City> */
    public function allActive(): array
    {
        return City::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function findOrFail(string $id): City
    {
        return City::findOrFail($id);
    }

    public function findBySlug(string $slug): ?City
    {
        return City::query()->where('slug', $slug)->first();
    }

    public function create(array $attributes): City
    {
        return City::create($attributes);
    }

    public function update(City $city, array $attributes): City
    {
        $city->update($attributes);

        return $city->refresh();
    }

    public function delete(City $city): void
    {
        $city->delete();
    }

    public function countDependents(City $city): int
    {
        return $city->stations()->count()
            + $city->departures()->count()
            + $city->arrivals()->count();
    }
}
