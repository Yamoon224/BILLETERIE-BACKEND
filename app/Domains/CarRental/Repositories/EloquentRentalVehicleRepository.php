<?php

namespace App\Domains\CarRental\Repositories;

use App\Domains\CarRental\Contracts\RentalVehicleRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\RentalVehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentRentalVehicleRepository implements RentalVehicleRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'brand' => 'brand',
        'price' => 'price_per_day',
        'seats' => 'seats',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, RentalVehicle> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->when($filters['partner_id'] ?? null, fn ($query, $id) => $query->where('partner_id', $id))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'created_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return LengthAwarePaginator<int, RentalVehicle> */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->where('is_active', true)
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'created_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): RentalVehicle
    {
        return RentalVehicle::query()->with(['partner:id,name,phone,whatsapp', 'city:id,name,slug'])->findOrFail($id);
    }

    public function create(array $attributes): RentalVehicle
    {
        return RentalVehicle::create($attributes);
    }

    public function update(RentalVehicle $rentalVehicle, array $attributes): RentalVehicle
    {
        $rentalVehicle->update($attributes);

        return $rentalVehicle->refresh();
    }

    public function delete(RentalVehicle $rentalVehicle): void
    {
        $rentalVehicle->delete();
    }

    /**
     * Filtres partages entre la liste d'administration et la recherche
     * publique : seule la borne de statut differe entre les deux.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<RentalVehicle>
     */
    private function baseQuery(array $filters): Builder
    {
        return RentalVehicle::query()
            ->with(['partner:id,name', 'city:id,name,slug'])
            ->when($filters['city_id'] ?? null, fn ($query, $id) => $query->where('city_id', $id))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['transmission'] ?? null, fn ($query, $t) => $query->where('transmission', $t))
            ->when($filters['seats'] ?? null, fn ($query, $seats) => $query->where('seats', '>=', $seats))
            ->when($filters['min_price'] ?? null, fn ($query, $price) => $query->where('price_per_day', '>=', $price))
            ->when($filters['max_price'] ?? null, fn ($query, $price) => $query->where('price_per_day', '<=', $price))
            ->when(
                array_key_exists('with_driver_available', $filters) && $filters['with_driver_available'] !== null,
                fn ($query) => $query->where('with_driver_available', $filters['with_driver_available']),
            )
            ->when(
                array_key_exists('is_featured', $filters) && $filters['is_featured'] !== null,
                fn ($query) => $query->where('is_featured', $filters['is_featured']),
            )
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%"),
            ));
    }
}
