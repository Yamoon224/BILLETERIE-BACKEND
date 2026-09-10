<?php

namespace App\Domains\Network\Repositories;

use App\Domains\Network\Contracts\CompanyRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentCompanyRepository implements CompanyRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'code' => 'code',
        'name' => 'name',
        'commission' => 'commission_per_mille',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Company> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Company::query()
            ->withCount(['vehicles', 'itineraries', 'stations'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"),
            ))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            // Une compagnie ne voit qu'elle-meme : le filtre vient du jeton de
            // l'appelant, jamais de la requete.
            ->when($filters['id'] ?? null, fn ($query, $id) => $query->where('id', $id))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'name', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Company
    {
        return Company::query()
            ->withCount(['vehicles', 'itineraries', 'stations', 'trips'])
            ->findOrFail($id);
    }

    public function create(array $attributes): Company
    {
        return Company::create($attributes);
    }

    public function update(Company $company, array $attributes): Company
    {
        $company->update($attributes);

        return $company->refresh();
    }

    public function delete(Company $company): void
    {
        $company->delete();
    }

    public function countDependents(Company $company): int
    {
        return $company->trips()->count()
            + $company->bookings()->count()
            + $company->users()->count();
    }
}
