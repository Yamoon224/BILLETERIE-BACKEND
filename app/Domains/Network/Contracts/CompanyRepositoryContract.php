<?php

namespace App\Domains\Network\Contracts;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CompanyRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Company>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Company;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Company;

    /** @param  array<string, mixed>  $attributes */
    public function update(Company $company, array $attributes): Company;

    public function delete(Company $company): void;

    /**
     * Nombre d'objets d'exploitation rattaches a la compagnie.
     *
     * Sert au refus de suppression : une compagnie qui a vendu des billets ne
     * disparait pas du referentiel, sans quoi un billet de l'an dernier ne
     * dirait plus qui l'a emis.
     */
    public function countDependents(Company $company): int;
}
