<?php

namespace App\Domains\Network\Contracts;

use App\Models\City;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CityRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, City>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Toutes les villes actives, pour alimenter les selecteurs de recherche.
     *
     * Non paginee a dessein : le referentiel compte quelques dizaines
     * d'entrees, et paginer un selecteur de ville obligerait le frontend a
     * charger la suite pendant que l'utilisateur tape.
     *
     * @return list<City>
     */
    public function allActive(): array;

    public function findOrFail(string $id): City;

    public function findBySlug(string $slug): ?City;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): City;

    /** @param  array<string, mixed>  $attributes */
    public function update(City $city, array $attributes): City;

    public function delete(City $city): void;

    public function countDependents(City $city): int;
}
