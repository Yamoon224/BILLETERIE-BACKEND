<?php

namespace App\Domains\Housing\Contracts;

use App\Models\Apartment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ApartmentRepositoryContract
{
    /**
     * Liste d'administration : tous statuts, bornee par partenaire cote
     * appelant.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Apartment>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Recherche publique : uniquement les fiches actives.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Apartment>
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Apartment;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Apartment;

    /** @param  array<string, mixed>  $attributes */
    public function update(Apartment $apartment, array $attributes): Apartment;

    public function delete(Apartment $apartment): void;
}
