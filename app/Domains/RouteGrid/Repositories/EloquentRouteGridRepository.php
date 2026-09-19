<?php

namespace App\Domains\RouteGrid\Repositories;

use App\Domains\RouteGrid\Contracts\RouteGridRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\RouteGridEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentRouteGridRepository implements RouteGridRepositoryContract
{
    /**
     * Le tri par ville passe par une sous-requete : la grille ne stocke que
     * l'identifiant, et un tri sur un UUID n'aurait aucun sens a l'ecran.
     *
     * @var array<string, string|array{0: string, 1: string}>
     */
    private const SORTABLE = [
        'origin' => ['raw', '(select name from cities where cities.id = route_grid_entries.origin_city_id)'],
        'destination' => ['raw', '(select name from cities where cities.id = route_grid_entries.destination_city_id)'],
        'price' => 'price',
        'duration' => 'duration_minutes',
        'distance' => 'distance_km',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, RouteGridEntry> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return RouteGridEntry::query()
            ->with(['originCity', 'destinationCity'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('company_name', 'like', "%{$search}%")
                    ->orWhereHas('originCity', fn ($city) => $city->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('destinationCity', fn ($city) => $city->where('name', 'like', "%{$search}%")),
            ))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'created_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return list<RouteGridEntry> */
    public function activeForRoute(string $originSlug, string $destinationSlug): array
    {
        return RouteGridEntry::query()
            ->with(['originCity', 'destinationCity'])
            ->where('is_active', true)
            ->whereHas('originCity', fn ($city) => $city->where('slug', $originSlug))
            ->whereHas('destinationCity', fn ($city) => $city->where('slug', $destinationSlug))
            ->orderBy('price')
            ->orderBy('company_name')
            ->get()
            ->all();
    }

    public function findOrFail(string $id): RouteGridEntry
    {
        return RouteGridEntry::with(['originCity', 'destinationCity'])->findOrFail($id);
    }

    public function create(array $attributes): RouteGridEntry
    {
        return $this->findOrFail(RouteGridEntry::create($attributes)->id);
    }

    public function update(RouteGridEntry $entry, array $attributes): RouteGridEntry
    {
        $entry->update($attributes);

        return $this->findOrFail($entry->id);
    }

    public function delete(RouteGridEntry $entry): void
    {
        $entry->delete();
    }
}
