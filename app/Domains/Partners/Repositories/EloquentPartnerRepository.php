<?php

namespace App\Domains\Partners\Repositories;

use App\Domains\Partners\Contracts\PartnerRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Partner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentPartnerRepository implements PartnerRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'name' => 'name',
        'type' => 'type',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Partner> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Partner::query()
            ->with('city:id,name,slug')
            ->withCount(['apartments', 'rentalVehicles'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            ))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            // Un gestionnaire de partenaire ne « liste » que le sien : le
            // filtre vient du jeton de l'appelant, jamais de la requete.
            ->when($filters['id'] ?? null, fn ($query, $id) => $query->where('id', $id))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'name', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Partner
    {
        return Partner::query()
            ->with('city:id,name,slug')
            ->withCount(['apartments', 'rentalVehicles'])
            ->findOrFail($id);
    }

    public function create(array $attributes): Partner
    {
        return Partner::create($attributes);
    }

    public function update(Partner $partner, array $attributes): Partner
    {
        $partner->update($attributes);

        return $partner->refresh();
    }

    public function delete(Partner $partner): void
    {
        $partner->delete();
    }

    public function countDependents(Partner $partner): int
    {
        return $partner->apartments()->count() + $partner->rentalVehicles()->count();
    }
}
