<?php

namespace Tests\Unit\Network;

use App\Domains\Network\Support\SeatMap;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Le plan de salle est derive de deux entiers. Ces tests fixent la
 * numerotation : elle figure sur des billets imprimes, et la changer
 * invaliderait la place d'un voyageur deja parti.
 */
class SeatMapTest extends TestCase
{
    #[Test]
    public function il_numerote_les_places_par_rangee_puis_par_lettre(): void
    {
        $map = new SeatMap(capacity: 8, seatsPerRow: 4);

        $this->assertSame(
            ['1A', '1B', '1C', '1D', '2A', '2B', '2C', '2D'],
            $map->seatNumbers(),
        );
    }

    #[Test]
    public function la_derniere_rangee_peut_etre_incomplete(): void
    {
        // Un 70 places en 2+2 finit sur deux sieges : le plan ne doit pas
        // inventer les deux places manquantes, sinon elles seraient vendues.
        $map = new SeatMap(capacity: 70, seatsPerRow: 4);

        $seats = $map->seatNumbers();

        $this->assertCount(70, $seats);
        $this->assertSame('18B', end($seats));
        $this->assertSame(18, $map->rowCount());
        $this->assertFalse($map->has('18C'));
    }

    #[Test]
    public function le_couloir_est_place_au_milieu_de_la_rangee(): void
    {
        $rows = (new SeatMap(capacity: 10, seatsPerRow: 5))->rows();

        // Sur un 3+2, le couloir tombe apres la deuxieme place.
        $this->assertSame(2, $rows[0]['aisle_after']);
        $this->assertSame(['1A', '1B', '1C', '1D', '1E'], $rows[0]['seats']);
    }

    #[Test]
    public function il_reconnait_une_place_existante_sans_tenir_compte_de_la_casse(): void
    {
        $map = new SeatMap(capacity: 20, seatsPerRow: 4);

        $this->assertTrue($map->has('3c'));
        $this->assertFalse($map->has('9A'));
    }

    #[Test]
    public function il_refuse_une_configuration_impossible(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SeatMap(capacity: 0, seatsPerRow: 4);
    }
}
