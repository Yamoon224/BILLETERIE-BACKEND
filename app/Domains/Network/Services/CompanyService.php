<?php

namespace App\Domains\Network\Services;

use App\Domains\Network\Contracts\CompanyRepositoryContract;
use App\Domains\Network\Exceptions\ResourceInUseException;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Gestion des compagnies partenaires, reservee a l'administrateur plateforme.
 */
final class CompanyService
{
    public function __construct(private readonly CompanyRepositoryContract $companies) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Company>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->companies->paginate($filters, $perPage);
    }

    public function find(string $id): Company
    {
        return $this->companies->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Company
    {
        return $this->companies->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Company $company, array $data): Company
    {
        return $this->companies->update($company, $data);
    }

    /** @throws ResourceInUseException */
    public function delete(Company $company): void
    {
        $dependents = $this->companies->countDependents($company);

        if ($dependents > 0) {
            throw ResourceInUseException::make('La compagnie', $company->name, $dependents);
        }

        $this->companies->delete($company);
    }
}
