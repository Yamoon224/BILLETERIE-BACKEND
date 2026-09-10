<?php

namespace App\Domains\Reporting\Repositories;

use App\Domains\Booking\Enums\BookingChannel;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Payments\Enums\PaymentMethod;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Reporting\Contracts\SalesReportReaderContract;
use App\Domains\Reporting\DTOs\ReportFilters;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Agregations du suivi d'activite.
 *
 * Une convention traverse tout ce fichier : **seules les reservations
 * confirmees comptent comme recette**. Une reservation expiree n'a jamais ete
 * payee et une reservation annulee a ete rendue ; les inclure gonflerait le
 * chiffre d'affaires d'un montant qui n'a jamais existe. Elles sont comptees
 * separement, parce qu'un taux d'abandon eleve est en soi un indicateur — il
 * signale souvent un delai de paiement trop court plutot qu'une desaffection.
 *
 * Le taux de remplissage se calcule sur les **billets** et non sur les
 * reservations : une reservation de quatre places n'occupe pas un siege.
 */
final class EloquentSalesReportReader implements SalesReportReaderContract
{
    /** @return array<string, int> */
    public function summary(ReportFilters $filters): array
    {
        $confirmed = $this->confirmedBookings($filters);

        /** @var object{bookings: int|null, tickets: int|null, gross: int|null, commission: int|null} $row */
        $row = (clone $confirmed)
            ->selectRaw('COUNT(*) as bookings, SUM(seats_count) as tickets, SUM(total_amount) as gross, SUM(commission_amount) as commission')
            ->first();

        $bookings = (int) ($row->bookings ?? 0);
        $gross = (int) ($row->gross ?? 0);
        $commission = (int) ($row->commission ?? 0);

        return [
            'bookings' => $bookings,
            'tickets' => (int) ($row->tickets ?? 0),
            'gross_revenue' => $gross,
            'commission' => $commission,
            'net_revenue' => $gross - $commission,
            // Panier moyen arrondi au franc : une moyenne a deux decimales sur
            // une devise qui n'en a aucune donnerait une fausse precision.
            'average_basket' => $bookings > 0 ? (int) round($gross / $bookings) : 0,
            'cancelled_bookings' => $this->countWithStatus($filters, BookingStatus::Cancelled),
            'expired_bookings' => $this->countWithStatus($filters, BookingStatus::Expired),
        ];
    }

    /** @return list<array{method: string, label: string, count: int, amount: int}> */
    public function revenueByPaymentMethod(ReportFilters $filters): array
    {
        $rows = Payment::query()
            ->select('method', DB::raw('COUNT(*) as operations'), DB::raw('SUM(amount) as amount'))
            ->where('status', PaymentStatus::Succeeded)
            ->whereBetween('paid_at', [$filters->from, $filters->to])
            ->when($filters->companyId, fn ($query, $companyId) => $query->whereHas(
                'booking',
                fn ($booking) => $booking->where('company_id', $companyId),
            ))
            ->when($filters->stationId, fn ($query, $stationId) => $query->whereHas(
                'booking',
                fn ($booking) => $booking->where('station_id', $stationId),
            ))
            ->groupBy('method')
            ->get();

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(string) $row->getAttribute('method')->value] = [
                'count' => (int) $row->getAttribute('operations'),
                'amount' => (int) $row->getAttribute('amount'),
            ];
        }

        // Tous les moyens sont rendus, y compris a zero : une ligne « especes :
        // 0 » se lit, une ligne absente laisse croire a un oubli de filtre.
        return array_map(static fn (PaymentMethod $method) => [
            'method' => $method->value,
            'label' => $method->label(),
            'count' => $indexed[$method->value]['count'] ?? 0,
            'amount' => $indexed[$method->value]['amount'] ?? 0,
        ], PaymentMethod::cases());
    }

    /** @return list<array{channel: string, label: string, bookings: int, tickets: int, amount: int}> */
    public function salesByChannel(ReportFilters $filters): array
    {
        $rows = $this->confirmedBookings($filters)
            ->select('channel', DB::raw('COUNT(*) as bookings'), DB::raw('SUM(seats_count) as tickets'), DB::raw('SUM(total_amount) as amount'))
            ->groupBy('channel')
            ->get();

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(string) $row->getAttribute('channel')->value] = [
                'bookings' => (int) $row->getAttribute('bookings'),
                'tickets' => (int) $row->getAttribute('tickets'),
                'amount' => (int) $row->getAttribute('amount'),
            ];
        }

        return array_map(static fn (BookingChannel $channel) => [
            'channel' => $channel->value,
            'label' => $channel->label(),
            'bookings' => $indexed[$channel->value]['bookings'] ?? 0,
            'tickets' => $indexed[$channel->value]['tickets'] ?? 0,
            'amount' => $indexed[$channel->value]['amount'] ?? 0,
        ], BookingChannel::cases());
    }

    /** @return list<array{date: string, bookings: int, tickets: int, amount: int}> */
    public function dailyRevenue(ReportFilters $filters): array
    {
        return $this->confirmedBookings($filters)
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as bookings'),
                DB::raw('SUM(seats_count) as tickets'),
                DB::raw('SUM(total_amount) as amount'),
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(static fn ($row) => [
                'date' => (string) $row->getAttribute('day'),
                'bookings' => (int) $row->getAttribute('bookings'),
                'tickets' => (int) $row->getAttribute('tickets'),
                'amount' => (int) $row->getAttribute('amount'),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function occupancyByTrip(ReportFilters $filters, int $limit = 100): array
    {
        return Trip::query()
            ->with(['itinerary.originCity:id,name', 'itinerary.destinationCity:id,name'])
            // Le remplissage se compte en billets occupant une place : un
            // billet annule a rendu la sienne, il ne remplit plus rien.
            ->withCount(['tickets as sold_seats' => fn (Builder $query) => $query->whereNotNull('seat_number')])
            ->withSum([
                'bookings as trip_revenue' => fn (Builder $query) => $query->where('status', BookingStatus::Confirmed),
            ], 'total_amount')
            ->whereBetween('departs_at', [$filters->from, $filters->departuresUntil()])
            ->when($filters->companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters->stationId, fn ($query, $stationId) => $query->where('departure_station_id', $stationId))
            ->orderByDesc('departs_at')
            ->limit($limit)
            ->get()
            ->map(static function (Trip $trip): array {
                $sold = (int) $trip->getAttribute('sold_seats');

                return [
                    'trip_id' => $trip->id,
                    'reference' => $trip->reference,
                    'departs_at' => $trip->departs_at->toIso8601String(),
                    'origin' => $trip->itinerary->originCity->name,
                    'destination' => $trip->itinerary->destinationCity->name,
                    'capacity' => $trip->seat_capacity,
                    'sold' => $sold,
                    'occupancy_rate' => $trip->seat_capacity > 0
                        ? round($sold / $trip->seat_capacity, 4)
                        : 0.0,
                    'revenue' => (int) ($trip->getAttribute('trip_revenue') ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{user_id: string, name: string, bookings: int, tickets: int, amount: int}> */
    public function salesByAgent(ReportFilters $filters): array
    {
        return $this->confirmedBookings($filters)
            ->whereNotNull('sold_by_user_id')
            ->join('users', 'users.id', '=', 'bookings.sold_by_user_id')
            ->select(
                'bookings.sold_by_user_id',
                'users.name',
                DB::raw('COUNT(*) as bookings'),
                DB::raw('SUM(bookings.seats_count) as tickets'),
                DB::raw('SUM(bookings.total_amount) as amount'),
            )
            ->groupBy('bookings.sold_by_user_id', 'users.name')
            ->orderByDesc('amount')
            ->get()
            ->map(static fn ($row) => [
                'user_id' => (string) $row->getAttribute('sold_by_user_id'),
                'name' => (string) $row->getAttribute('name'),
                'bookings' => (int) $row->getAttribute('bookings'),
                'tickets' => (int) $row->getAttribute('tickets'),
                'amount' => (int) $row->getAttribute('amount'),
            ])
            ->values()
            ->all();
    }

    /** @return Builder<Booking> */
    private function confirmedBookings(ReportFilters $filters): Builder
    {
        return Booking::query()
            ->where('bookings.status', BookingStatus::Confirmed)
            ->whereBetween('bookings.created_at', [$filters->from, $filters->to])
            ->when($filters->companyId, fn ($query, $companyId) => $query->where('bookings.company_id', $companyId))
            ->when($filters->stationId, fn ($query, $stationId) => $query->where('bookings.station_id', $stationId));
    }

    private function countWithStatus(ReportFilters $filters, BookingStatus $status): int
    {
        return Booking::query()
            ->where('status', $status)
            ->whereBetween('created_at', [$filters->from, $filters->to])
            ->when($filters->companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters->stationId, fn ($query, $stationId) => $query->where('station_id', $stationId))
            ->count();
    }
}
