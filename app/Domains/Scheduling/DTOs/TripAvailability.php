<?php

namespace App\Domains\Scheduling\DTOs;

use App\Models\Trip;

/**
 * Un depart et l'etat de son remplissage.
 *
 * Le nombre de places disponibles n'est pas une colonne : il se calcule a
 * chaque lecture depuis les billets emis. Un compteur stocke sur une table qui
 * recoit des ventes concurrentes au guichet et en ligne derive au premier
 * incident, et un compteur faux se paie en voyageurs debout dans le couloir.
 *
 * Le taux de remplissage est expose ici plutot que recalcule par le frontend :
 * c'est un indicateur du tableau de bord des gestionnaires, et deux
 * definitions differentes du meme taux — l'une cote serveur, l'autre cote
 * interface — finiraient par s'afficher cote a cote.
 */
final readonly class TripAvailability
{
    public function __construct(
        public Trip $trip,
        public int $seatsTaken,
    ) {}

    public function seatsAvailable(): int
    {
        return max(0, $this->trip->seat_capacity - $this->seatsTaken);
    }

    public function isFull(): bool
    {
        return $this->seatsAvailable() === 0;
    }

    /** Taux de remplissage, entre 0 et 1. */
    public function occupancyRate(): float
    {
        if ($this->trip->seat_capacity === 0) {
            return 0.0;
        }

        return round($this->seatsTaken / $this->trip->seat_capacity, 4);
    }
}
