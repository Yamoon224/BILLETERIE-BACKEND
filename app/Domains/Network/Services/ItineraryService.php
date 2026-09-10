<?php

namespace App\Domains\Network\Services;

use App\Domains\Network\Contracts\ItineraryRepositoryContract;
use App\Domains\Network\Exceptions\ResourceInUseException;
use App\Models\Itinerary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Itineraires exploites par une compagnie.
 *
 * Le tarif de reference est modifiable a tout moment : il ne sert qu'a
 * proposer un prix par defaut aux departs a venir. Les departs deja programmes
 * conservent le leur, copie a leur creation — reviser un tarif ne reecrit donc
 * jamais le prix d'un billet vendu.
 */
final class ItineraryService
{
    public function __construct(private readonly ItineraryRepositoryContract $itineraries) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Itinerary>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->itineraries->paginate($filters, $perPage);
    }

    public function find(string $id): Itinerary
    {
        return $this->itineraries->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Itinerary
    {
        return $this->itineraries->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Itinerary $itinerary, array $data): Itinerary
    {
        return $this->itineraries->update($itinerary, $data);
    }

    /** @throws ResourceInUseException */
    public function delete(Itinerary $itinerary): void
    {
        $dependents = $this->itineraries->countDependents($itinerary);

        if ($dependents > 0) {
            $label = $itinerary->originCity->name.' vers '.$itinerary->destinationCity->name;

            throw ResourceInUseException::make('L itineraire', $label, $dependents);
        }

        $this->itineraries->delete($itinerary);
    }
}
