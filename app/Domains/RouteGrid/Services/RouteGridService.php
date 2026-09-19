<?php

namespace App\Domains\RouteGrid\Services;

use App\Domains\RouteGrid\Contracts\RouteGridRepositoryContract;
use App\Models\RouteGridEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Grille des trajets, editee par l'administrateur de la plateforme.
 *
 * Elle renseigne le voyageur sur les liaisons qui ne sont pas encore
 * reservables en ligne. Aucune reservation ne s'y adosse : la grille ne
 * touche ni aux departs, ni aux billets, ni aux paiements.
 */
final class RouteGridService
{
    public function __construct(private readonly RouteGridRepositoryContract $entries) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RouteGridEntry>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->entries->paginate($filters, $perPage);
    }

    /** @return list<RouteGridEntry> */
    public function forRoute(string $originSlug, string $destinationSlug): array
    {
        return $this->entries->activeForRoute($originSlug, $destinationSlug);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): RouteGridEntry
    {
        return $this->entries->create($this->normalized($data));
    }

    /** @param  array<string, mixed>  $data */
    public function update(RouteGridEntry $entry, array $data): RouteGridEntry
    {
        return $this->entries->update($entry, $this->normalized($data));
    }

    public function delete(RouteGridEntry $entry): void
    {
        $this->entries->delete($entry);
    }

    /**
     * Horaires tries et sans doublon : l'administrateur les saisit dans le
     * desordre, le voyageur les lit de gauche a droite comme une journee.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalized(array $data): array
    {
        if (isset($data['departure_times']) && is_array($data['departure_times'])) {
            $times = array_values(array_unique($data['departure_times']));
            sort($times);
            $data['departure_times'] = $times;
        }

        return $data;
    }
}
