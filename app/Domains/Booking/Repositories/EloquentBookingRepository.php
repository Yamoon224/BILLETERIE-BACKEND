<?php

namespace App\Domains\Booking\Repositories;

use App\Domains\Booking\Contracts\BookingRepositoryContract;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Shared\Support\Sort;
use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class EloquentBookingRepository implements BookingRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'reference' => 'reference',
        'customer' => 'customer_name',
        'seats' => 'seats_count',
        'amount' => 'total_amount',
        'status' => 'status',
        'channel' => 'channel',
        'created_at' => 'created_at',
        'departs_at' => ['raw', '(select departs_at from trips where trips.id = bookings.trip_id)'],
    ];

    /** @return LengthAwarePaginator<int, Booking> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Booking::query()
            ->with([
                'trip:id,reference,departs_at,itinerary_id,company_id',
                'trip.itinerary.originCity:id,name,slug',
                'trip.itinerary.destinationCity:id,name,slug',
                'company:id,code,name',
                'soldBy:id,name',
            ])
            ->withCount('tickets')
            ->when($filters['company_id'] ?? null, fn ($query, $id) => $query->where('company_id', $id))
            ->when($filters['trip_id'] ?? null, fn ($query, $id) => $query->where('trip_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['channel'] ?? null, fn ($query, $channel) => $query->where('channel', $channel))
            ->when($filters['sold_by_user_id'] ?? null, fn ($query, $id) => $query->where('sold_by_user_id', $id))
            ->when($filters['station_id'] ?? null, fn ($query, $id) => $query->where('station_id', $id))
            ->when($filters['customer_user_id'] ?? null, fn ($query, $id) => $query->where('customer_user_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%"),
            ))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'created_at', 'desc'))
            // La page peut venir des filtres plutot que de la requete HTTP :
            // l export parcourt la liste par lots hors de tout contexte HTTP,
            // et sans cela il relirait indefiniment la premiere page.
            ->paginate($perPage, ['*'], 'page', isset($filters['page']) ? (int) $filters['page'] : null)
            ->withQueryString();
    }

    public function findOrFail(string $id): Booking
    {
        return Booking::query()
            ->with([
                'trip.itinerary.originCity',
                'trip.itinerary.destinationCity',
                'trip.departureStation',
                'trip.arrivalStation',
                'company',
                'tickets',
                'payments',
                'soldBy:id,name',
            ])
            ->findOrFail($id);
    }

    public function findByReference(string $reference): ?Booking
    {
        return Booking::query()
            ->with([
                'trip.itinerary.originCity',
                'trip.itinerary.destinationCity',
                'trip.departureStation',
                'tickets',
                'payments',
            ])
            ->where('reference', $reference)
            ->first();
    }

    public function findByClientReference(string $clientReference): ?Booking
    {
        return Booking::query()
            ->with(['tickets', 'payments'])
            ->where('client_reference', $clientReference)
            ->first();
    }

    public function create(array $attributes): Booking
    {
        return Booking::create($attributes);
    }

    public function update(Booking $booking, array $attributes): Booking
    {
        $booking->update($attributes);

        return $booking->refresh();
    }

    /** @return list<Booking> */
    public function expiredHolds(Carbon $now, int $limit = 200): array
    {
        return Booking::query()
            ->with('tickets')
            ->where('status', BookingStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            // Les plus anciennes d'abord : ce sont leurs places qui manquent
            // depuis le plus longtemps au plan de salle.
            ->orderBy('expires_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}
