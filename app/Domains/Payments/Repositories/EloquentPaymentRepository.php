<?php

namespace App\Domains\Payments\Repositories;

use App\Domains\Payments\Contracts\PaymentRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentPaymentRepository implements PaymentRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'reference' => 'reference',
        'amount' => 'amount',
        'method' => 'method',
        'status' => 'status',
        'paid_at' => 'paid_at',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Payment> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['booking:id,reference,company_id,customer_name,trip_id', 'collectedBy:id,name'])
            ->when($filters['booking_id'] ?? null, fn ($query, $id) => $query->where('booking_id', $id))
            ->when($filters['method'] ?? null, fn ($query, $method) => $query->where('method', $method))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->when($filters['company_id'] ?? null, fn ($query, $companyId) => $query->whereHas(
                'booking',
                fn ($booking) => $booking->where('company_id', $companyId),
            ))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%"),
            ))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'created_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Payment
    {
        return Payment::query()->with('booking')->findOrFail($id);
    }

    public function findByExternalReference(string $externalReference): ?Payment
    {
        return Payment::query()
            ->with('booking')
            ->where('external_reference', $externalReference)
            ->first();
    }

    public function findByClientReference(string $clientReference): ?Payment
    {
        return Payment::query()->where('client_reference', $clientReference)->first();
    }

    public function create(array $attributes): Payment
    {
        return Payment::create($attributes);
    }

    public function update(Payment $payment, array $attributes): Payment
    {
        $payment->update($attributes);

        return $payment->refresh();
    }

    public function lockByExternalReferenceForUpdate(string $externalReference): ?Payment
    {
        return Payment::query()
            ->where('external_reference', $externalReference)
            ->lockForUpdate()
            ->first();
    }
}
