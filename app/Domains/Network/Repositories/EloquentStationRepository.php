<?php

namespace App\Domains\Network\Repositories;

use App\Domains\Network\Contracts\StationRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Station;
use App\Models\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentStationRepository implements StationRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'name' => 'name',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
        'city' => ['raw', '(select name from cities where cities.id = stations.city_id)'],
    ];

    /** @return LengthAwarePaginator<int, Station> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Station::query()
            ->with(['city:id,name,slug', 'company:id,code,name'])
            ->when($filters['city_id'] ?? null, fn ($query, $id) => $query->where('city_id', $id))
            ->when(
                array_key_exists('company_id', $filters) && $filters['company_id'] !== null,
                // Une compagnie voit ses gares **et** les gares partagees :
                // ses departs peuvent partir des deux, et masquer les secondes
                // rendrait la moitie des gares routieres inselectionnables.
                fn ($query) => $query->where(
                    fn ($sub) => $sub
                        ->where('company_id', $filters['company_id'])
                        ->orWhereNull('company_id'),
                ),
            )
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'name', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Station
    {
        return Station::query()->with(['city', 'company'])->findOrFail($id);
    }

    public function create(array $attributes): Station
    {
        return Station::create($attributes);
    }

    public function update(Station $station, array $attributes): Station
    {
        $station->update($attributes);

        return $station->refresh();
    }

    public function delete(Station $station): void
    {
        $station->delete();
    }

    public function countDependents(Station $station): int
    {
        return Trip::query()
            ->where('departure_station_id', $station->id)
            ->orWhere('arrival_station_id', $station->id)
            ->count();
    }
}
