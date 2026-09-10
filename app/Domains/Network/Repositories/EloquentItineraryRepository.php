<?php

namespace App\Domains\Network\Repositories;

use App\Domains\Network\Contracts\ItineraryRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Itinerary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentItineraryRepository implements ItineraryRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'price' => 'base_price',
        'duration' => 'duration_minutes',
        'distance' => 'distance_km',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
        'origin' => ['raw', '(select name from cities where cities.id = itineraries.origin_city_id)'],
        'destination' => ['raw', '(select name from cities where cities.id = itineraries.destination_city_id)'],
    ];

    /** @return LengthAwarePaginator<int, Itinerary> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Itinerary::query()
            ->with(['originCity:id,name,slug', 'destinationCity:id,name,slug', 'company:id,code,name'])
            ->withCount('trips')
            ->when($filters['company_id'] ?? null, fn ($query, $id) => $query->where('company_id', $id))
            ->when($filters['origin_city_id'] ?? null, fn ($query, $id) => $query->where('origin_city_id', $id))
            ->when($filters['destination_city_id'] ?? null, fn ($query, $id) => $query->where('destination_city_id', $id))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->whereHas('originCity', fn ($city) => $city->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('destinationCity', fn ($city) => $city->where('name', 'like', "%{$search}%")),
            ))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'created_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Itinerary
    {
        return Itinerary::query()
            ->with(['originCity', 'destinationCity', 'company'])
            ->withCount('trips')
            ->findOrFail($id);
    }

    public function create(array $attributes): Itinerary
    {
        return Itinerary::create($attributes);
    }

    public function update(Itinerary $itinerary, array $attributes): Itinerary
    {
        $itinerary->update($attributes);

        return $itinerary->refresh();
    }

    public function delete(Itinerary $itinerary): void
    {
        $itinerary->delete();
    }

    public function countDependents(Itinerary $itinerary): int
    {
        return $itinerary->trips()->count();
    }
}
