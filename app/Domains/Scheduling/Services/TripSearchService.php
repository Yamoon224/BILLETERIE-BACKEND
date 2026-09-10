<?php

namespace App\Domains\Scheduling\Services;

use App\Domains\Scheduling\Contracts\TripRepositoryContract;
use App\Domains\Scheduling\DTOs\TripAvailability;
use App\Domains\Scheduling\DTOs\TripSearchCriteria;
use App\Domains\Ticketing\Contracts\OccupiedSeatReaderContract;
use App\Models\Trip;

/**
 * Recherche de departs par le voyageur.
 *
 * Le remplissage est resolu en **une** requete pour toute la page de
 * resultats, pas une par depart. Ce n'est pas une micro-optimisation : la
 * recherche est l'ecran le plus consulte de la plateforme, il s'ouvre sur un
 * telephone en 2G, et vingt requetes supplementaires y ajoutent une attente
 * qui se mesure en secondes.
 *
 * Le service ne connait pas les billets : il demande un decompte a
 * `OccupiedSeatReaderContract`, la lecture etroite exposee par Ticketing. Il
 * n'a donc aucun moyen d'emettre ni d'annuler un billet, ce qui est exactement
 * ce qu'on attend d'un service de recherche.
 */
final class TripSearchService
{
    public function __construct(
        private readonly TripRepositoryContract $trips,
        private readonly OccupiedSeatReaderContract $occupiedSeats,
    ) {}

    /**
     * @return list<TripAvailability>
     */
    public function search(TripSearchCriteria $criteria): array
    {
        $trips = $this->trips->search($criteria);

        if ($trips === []) {
            return [];
        }

        $counts = $this->occupiedSeats->occupiedSeatCounts(
            array_map(static fn (Trip $trip) => $trip->id, $trips),
        );

        $results = array_map(
            static fn (Trip $trip) => new TripAvailability($trip, $counts[$trip->id] ?? 0),
            $trips,
        );

        // Le filtre « places disponibles » s'applique apres le calcul et non
        // dans la requete : le nombre de places prises n'est pas une colonne,
        // et le reproduire en SQL creerait une seconde definition de la
        // disponibilite, condamnee a diverger de la premiere.
        $results = array_values(array_filter(
            $results,
            static fn (TripAvailability $availability) => ! $criteria->onlyAvailable
                || $availability->seatsAvailable() >= $criteria->passengers,
        ));

        return $results;
    }

    /** Etat de remplissage d'un depart precis. */
    public function availabilityFor(string $tripId): TripAvailability
    {
        $trip = $this->trips->findOrFail($tripId);

        return new TripAvailability(
            $trip,
            count($this->occupiedSeats->occupiedSeatNumbers($trip->id)),
        );
    }

    /**
     * Plan de salle d'un depart : chaque place, et si elle est prise.
     *
     * @return array{
     *     rows: list<array{row: int, seats: list<array{number: string, is_taken: bool}>, aisle_after: int}>,
     *     capacity: int,
     *     taken: int,
     *     available: int
     * }
     */
    public function seatMapFor(string $tripId): array
    {
        $trip = $this->trips->findOrFail($tripId);
        $taken = $this->occupiedSeats->occupiedSeatNumbers($trip->id);
        $takenIndex = array_flip($taken);

        $rows = array_map(
            static fn (array $row) => [
                'row' => $row['row'],
                'aisle_after' => $row['aisle_after'],
                'seats' => array_map(
                    static fn (string $seat) => [
                        'number' => $seat,
                        'is_taken' => isset($takenIndex[$seat]),
                    ],
                    $row['seats'],
                ),
            ],
            $trip->seatMap()->rows(),
        );

        return [
            'rows' => $rows,
            'capacity' => $trip->seat_capacity,
            'taken' => count($taken),
            'available' => max(0, $trip->seat_capacity - count($taken)),
        ];
    }
}
