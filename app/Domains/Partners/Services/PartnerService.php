<?php

namespace App\Domains\Partners\Services;

use App\Domains\Partners\Contracts\PartnerRepositoryContract;
use App\Domains\Partners\Exceptions\ResourceInUseException;
use App\Models\Partner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Gestion des partenaires (appartements, location auto).
 *
 * La creation et la suppression restent reservees a l'administrateur
 * plateforme (voir routes, permission `platform.manage`) : un partenaire ne
 * s'auto-inscrit pas, comme une compagnie de transport ne le fait pas non
 * plus. Un gestionnaire de partenaire ne consulte et ne modifie que sa propre
 * fiche.
 */
final class PartnerService
{
    public function __construct(private readonly PartnerRepositoryContract $partners) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Partner>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->partners->paginate($filters, $perPage);
    }

    public function find(string $id): Partner
    {
        return $this->partners->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Partner
    {
        return $this->partners->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Partner $partner, array $data): Partner
    {
        return $this->partners->update($partner, $data);
    }

    /** @throws ResourceInUseException */
    public function delete(Partner $partner): void
    {
        $dependents = $this->partners->countDependents($partner);

        if ($dependents > 0) {
            throw ResourceInUseException::make($partner->name, $dependents);
        }

        $this->partners->delete($partner);
    }
}
