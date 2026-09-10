<?php

namespace App\Domains\Scheduling\Repositories;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Scheduling\Contracts\TripRepositoryContract;
use App\Domains\Scheduling\DTOs\TripSearchCriteria;
use App\Domains\Scheduling\Enums\TripStatus;
use App\Domains\Shared\Support\Sort;
use App\Models\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentTripRepository implements TripRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'reference' => 'reference',
        'departs_at' => 'departs_at',
        'price' => 'price',
        'status' => 'status',
        'capacity' => 'seat_capacity',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Trip> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Trip::query()
            ->with([
                'itinerary.originCity:id,name,slug',
                'itinerary.destinationCity:id,name,slug',
                'vehicle:id,registration,model,class',
                'departureStation:id,name,city_id',
                'company:id,code,name',
            ])
            ->when($filters['company_id'] ?? null, fn ($query, $id) => $query->where('company_id', $id))
            ->when($filters['itinerary_id'] ?? null, fn ($query, $id) => $query->where('itinerary_id', $id))
            ->when($filters['vehicle_id'] ?? null, fn ($query, $id) => $query->where('vehicle_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('departs_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('departs_at', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('reference', 'like', "%{$search}%"))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'departs_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return list<Trip> */
    public function search(TripSearchCriteria $criteria): array
    {
        return Trip::query()
            ->with([
                'itinerary.originCity:id,name,slug',
                'itinerary.destinationCity:id,name,slug',
                'vehicle:id,registration,model,class',
                'departureStation:id,name,address,city_id',
                'arrivalStation:id,name,address,city_id',
                'company:id,code,name,logo_path',
            ])
            // Seuls les departs encore ouverts a la vente : un depart annule
            // dans une liste de resultats ferait perdre un voyageur.
            ->whereIn('status', [TripStatus::Scheduled->value, TripStatus::Boarding->value])
            ->whereBetween('departs_at', [$criteria->from(), $criteria->to()])
            ->whereHas('itinerary', fn ($itinerary) => $itinerary
                ->where('is_active', true)
                ->whereHas('originCity', fn ($city) => $city->where('slug', $criteria->originSlug))
                ->whereHas('destinationCity', fn ($city) => $city->where('slug', $criteria->destinationSlug)))
            ->when($criteria->companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            // Le voyageur lit une liste d'horaires : elle se trie par heure de
            // depart, jamais par prix ni par identifiant.
            ->orderBy('departs_at')
            ->limit($criteria->limit)
            ->get()
            ->all();
    }

    public function findOrFail(string $id): Trip
    {
        return Trip::query()
            ->with([
                'itinerary.originCity',
                'itinerary.destinationCity',
                'vehicle',
                'departureStation.city',
                'arrivalStation.city',
                'company',
            ])
            ->findOrFail($id);
    }

    public function lockForUpdate(string $id): ?Trip
    {
        return Trip::query()->where('id', $id)->lockForUpdate()->first();
    }

    public function create(array $attributes): Trip
    {
        return Trip::create($attributes);
    }

    public function update(Trip $trip, array $attributes): Trip
    {
        $trip->update($attributes);

        return $trip->refresh();
    }

    public function delete(Trip $trip): void
    {
        $trip->delete();
    }

    public function countActiveBookings(Trip $trip): int
    {
        return $trip->bookings()
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->count();
    }
}
