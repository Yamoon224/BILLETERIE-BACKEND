<?php

namespace App\Domains\Network\Support;

use InvalidArgumentException;

/**
 * Plan de salle d'un vehicule, deduit de deux entiers.
 *
 * Un car interurbain est une grille reguliere : des rangees numerotees, et
 * dans chaque rangee des places designees par une lettre, separees par un
 * couloir central. Tout le plan tient donc dans « combien de places » et
 * « combien de places par rangee » — stocker un plan JSON complet reviendrait
 * a conserver, pour chaque bus, une information entierement derivable, avec la
 * derive que cela suppose des qu'un vehicule est modifie.
 *
 * La derniere rangee peut etre incomplete (un 70 places en 2+2 finit sur deux
 * sieges) : elle est generee telle quelle, sans inventer de place inexistante.
 *
 * Le couloir est place au milieu de la rangee. Sur un 2+2 : A B | C D. Sur un
 * 3+2 : A B C | D E. C'est ce que le voyageur voit sur le plan, et c'est ce
 * qui lui permet de choisir un cote fenetre en connaissance de cause.
 */
final class SeatMap
{
    private const LETTERS = 'ABCDEFGH';

    public function __construct(
        private readonly int $capacity,
        private readonly int $seatsPerRow,
    ) {
        if ($capacity < 1) {
            throw new InvalidArgumentException('Un vehicule compte au moins une place.');
        }

        if ($seatsPerRow < 1 || $seatsPerRow > strlen(self::LETTERS)) {
            throw new InvalidArgumentException(
                'Une rangee compte entre 1 et '.strlen(self::LETTERS).' places.',
            );
        }
    }

    /**
     * Tous les numeros de place, dans l'ordre d'installation.
     *
     * @return list<string>
     */
    public function seatNumbers(): array
    {
        $seats = [];

        for ($index = 0; $index < $this->capacity; $index++) {
            $row = intdiv($index, $this->seatsPerRow) + 1;
            $seats[] = $row.self::LETTERS[$index % $this->seatsPerRow];
        }

        return $seats;
    }

    /**
     * Le plan structure en rangees, pour l'affichage du selecteur de place.
     *
     * `aisle_after` dit apres combien de sieges se trouve le couloir : le
     * frontend n'a ainsi aucune regle de plan a reimplementer, et un futur
     * vehicule a plan asymetrique ne demandera de changement qu'ici.
     *
     * @return list<array{row: int, seats: list<string>, aisle_after: int}>
     */
    public function rows(): array
    {
        $rows = [];
        $aisleAfter = intdiv($this->seatsPerRow, 2);

        foreach (array_chunk($this->seatNumbers(), $this->seatsPerRow) as $position => $seats) {
            $rows[] = [
                'row' => $position + 1,
                'seats' => $seats,
                'aisle_after' => $aisleAfter,
            ];
        }

        return $rows;
    }

    /** Ce numero de place existe-t-il reellement dans ce vehicule ? */
    public function has(string $seatNumber): bool
    {
        return in_array(strtoupper($seatNumber), $this->seatNumbers(), true);
    }

    public function rowCount(): int
    {
        return (int) ceil($this->capacity / $this->seatsPerRow);
    }

    public function capacity(): int
    {
        return $this->capacity;
    }
}
