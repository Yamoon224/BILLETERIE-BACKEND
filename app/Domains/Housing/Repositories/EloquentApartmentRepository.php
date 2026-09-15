<?php

namespace App\Domains\Housing\Repositories;

use App\Domains\Housing\Contracts\ApartmentRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Apartment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentApartmentRepository implements ApartmentRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'title' => 'title',
        'price' => 'price_per_night',
        'capacity' => 'capacity',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Apartment> */
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

    /** @return LengthAwarePaginator<int, Apartment> */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->where('is_active', true)
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'created_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Apartment
    {
        return Apartment::query()->with(['partner:id,name,phone,whatsapp', 'city:id,name,slug'])->findOrFail($id);
    }

    public function create(array $attributes): Apartment
    {
        return Apartment::create($attributes);
    }

    public function update(Apartment $apartment, array $attributes): Apartment
    {
        $apartment->update($attributes);

        return $apartment->refresh();
    }

    public function delete(Apartment $apartment): void
    {
        $apartment->delete();
    }

    /**
     * Filtres partages entre la liste d'administration et la recherche
     * publique : seule la borne de statut differe entre les deux.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Apartment>
     */
    private function baseQuery(array $filters): Builder
    {
        return Apartment::query()
            ->with(['partner:id,name', 'city:id,name,slug'])
            ->when($filters['city_id'] ?? null, fn ($query, $id) => $query->where('city_id', $id))
            ->when($filters['neighborhood'] ?? null, fn ($query, $n) => $query->where('neighborhood', 'like', "%{$n}%"))
            ->when($filters['capacity'] ?? null, fn ($query, $capacity) => $query->where('capacity', '>=', $capacity))
            ->when($filters['min_price'] ?? null, fn ($query, $price) => $query->where('price_per_night', '>=', $price))
            ->when($filters['max_price'] ?? null, fn ($query, $price) => $query->where('price_per_night', '<=', $price))
            ->when(
                array_key_exists('is_featured', $filters) && $filters['is_featured'] !== null,
                fn ($query) => $query->where('is_featured', $filters['is_featured']),
            )
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('neighborhood', 'like', "%{$search}%"),
            ));
    }
}
