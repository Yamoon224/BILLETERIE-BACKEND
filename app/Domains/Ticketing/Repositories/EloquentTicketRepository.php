<?php

namespace App\Domains\Ticketing\Repositories;

use App\Domains\Shared\Support\Sort;
use App\Domains\Ticketing\Contracts\TicketRepositoryContract;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class EloquentTicketRepository implements TicketRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'code' => 'code',
        'seat' => 'seat_number',
        'passenger' => 'passenger_name',
        'status' => 'status',
        'scanned_at' => 'scanned_at',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Ticket> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Ticket::query()
            ->with(['trip.itinerary.originCity', 'trip.itinerary.destinationCity', 'booking'])
            ->when($filters['trip_id'] ?? null, fn ($query, $tripId) => $query->where('trip_id', $tripId))
            ->when($filters['booking_id'] ?? null, fn ($query, $id) => $query->where('booking_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('passenger_name', 'like', "%{$search}%")
                    ->orWhere('passenger_phone', 'like', "%{$search}%"),
            ))
            // Cloisonnement par compagnie : applique ici, sur la requete, et
            // non filtre apres coup en PHP — une pagination filtree en memoire
            // afficherait des pages a moitie vides sans que rien ne le dise.
            ->when($filters['company_id'] ?? null, fn ($query, $companyId) => $query->whereHas(
                'trip',
                fn ($trip) => $trip->where('company_id', $companyId),
            ))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'created_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByCode(string $code): ?Ticket
    {
        return Ticket::query()
            ->with(['trip.itinerary.originCity', 'trip.itinerary.destinationCity', 'trip.departureStation', 'booking'])
            ->where('code', $code)
            ->first();
    }

    public function lockByCodeForUpdate(string $code): ?Ticket
    {
        return Ticket::query()
            ->where('code', $code)
            ->lockForUpdate()
            ->first();
    }

    public function create(array $attributes): Ticket
    {
        return Ticket::create($attributes);
    }

    public function markScanned(
        Ticket $ticket,
        ?string $scannedByUserId,
        ?string $stationId,
        ?string $scanClientReference,
    ): Ticket {
        $ticket->forceFill([
            'status' => TicketStatus::Used,
            'scanned_at' => Carbon::now(),
            'scanned_by_user_id' => $scannedByUserId,
            'scanned_station_id' => $stationId,
            'scan_client_reference' => $scanClientReference,
        ])->save();

        return $ticket;
    }

    public function releaseSeat(Ticket $ticket, TicketStatus $status): Ticket
    {
        $ticket->forceFill([
            'status' => $status,
            // La place part dans la colonne « liberee » avant d'etre videe :
            // sans cela, un billet annule ne dirait plus quelle place il
            // occupait, et une reclamation deviendrait invérifiable.
            'released_seat_number' => $ticket->seat_number ?? $ticket->released_seat_number,
            'seat_number' => null,
        ])->save();

        return $ticket;
    }

    /** @return list<Ticket> */
    public function forTrip(string $tripId): array
    {
        return Ticket::query()
            ->where('trip_id', $tripId)
            ->with('booking:id,reference,customer_phone,channel')
            ->orderByRaw('CAST(seat_number AS UNSIGNED), seat_number')
            ->get()
            ->all();
    }
}
