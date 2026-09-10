<?php

namespace App\Domains\Network\Services;

use App\Domains\Network\Contracts\CityRepositoryContract;
use App\Domains\Network\Exceptions\ResourceInUseException;
use App\Models\City;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Referentiel des villes desservies.
 *
 * Partage entre toutes les compagnies : deux compagnies qui desservent Bouake
 * pointent la meme fiche, sans quoi une recherche « Abidjan vers Bouake » ne
 * trouverait que la moitie des departs.
 */
final class CityService
{
    public function __construct(private readonly CityRepositoryContract $cities) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, City>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->cities->paginate($filters, $perPage);
    }

    /** @return list<City> */
    public function allActive(): array
    {
        return $this->cities->allActive();
    }

    public function find(string $id): City
    {
        return $this->cities->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): City
    {
        return $this->cities->create($this->withSlug($data));
    }

    /** @param  array<string, mixed>  $data */
    public function update(City $city, array $data): City
    {
        // Le slug ne se regenere pas au renommage : il vit dans des liens de
        // recherche deja partages par messagerie, et le changer casserait
        // silencieusement chacun d'eux. Il ne bouge que sur demande explicite.
        return $this->cities->update($city, $data);
    }

    /** @throws ResourceInUseException */
    public function delete(City $city): void
    {
        $dependents = $this->cities->countDependents($city);

        if ($dependents > 0) {
            throw ResourceInUseException::make('La ville', $city->name, $dependents);
        }

        $this->cities->delete($city);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSlug(array $data): array
    {
        $data['slug'] ??= Str::slug((string) $data['name']);

        return $data;
    }
}
