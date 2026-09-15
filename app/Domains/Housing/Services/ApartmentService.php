<?php

namespace App\Domains\Housing\Services;

use App\Domains\Housing\Contracts\ApartmentRepositoryContract;
use App\Models\Apartment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Catalogue d'appartements meubles.
 *
 * Aucune reservation en ligne pour ce catalogue a ce stade : ni disponibilite
 * ni prix ne sont figes par une vente, contrairement a un depart de bus. Une
 * fiche se modifie donc librement, y compris son tarif, sans regle de
 * gel a surveiller ici.
 */
final class ApartmentService
{
    public function __construct(private readonly ApartmentRepositoryContract $apartments) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Apartment>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->apartments->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Apartment>
     */
    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->apartments->search($filters, $perPage);
    }

    public function find(string $id): Apartment
    {
        return $this->apartments->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Apartment
    {
        return $this->apartments->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Apartment $apartment, array $data): Apartment
    {
        return $this->apartments->update($apartment, $data);
    }

    public function delete(Apartment $apartment): void
    {
        $this->apartments->delete($apartment);
    }
}
