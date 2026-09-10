<?php

namespace Tests\Unit\Shared;

use App\Domains\Shared\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * L'arrondi de la commission est au plus proche, pas tronque : tronquer
     * ferait perdre systematiquement moins d'un franc a la plateforme sur
     * chaque vente.
     */
    #[Test]
    #[DataProvider('commissions')]
    public function il_arrondit_la_commission_au_franc_le_plus_proche(int $amount, int $perMille, int $expected): void
    {
        $this->assertSame($expected, Money::commission($amount, $perMille));
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function commissions(): iterable
    {
        yield 'taux nul' => [7000, 0, 0];
        yield '2,5 % rond' => [8000, 25, 200];
        yield 'arrondi vers le haut' => [7000, 25, 175];
        // 5000 x 2,3 % = 115 exactement.
        yield 'taux atypique' => [5000, 23, 115];
        // 999 x 2,5 % = 24,975 -> 25, et non 24 comme le donnerait une troncature.
        yield 'moitie superieure' => [999, 25, 25];
        yield 'montant nul' => [0, 25, 0];
    }

    #[Test]
    public function il_refuse_un_taux_hors_bornes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::commission(7000, 1001);
    }

    #[Test]
    public function il_refuse_une_assiette_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::commission(-1, 25);
    }

    /**
     * Une agregation SQL revient en chaine selon le pilote : le total doit
     * rester entier, sans quoi une recette de journee finirait par porter des
     * centimes qui n'existent pas en franc CFA.
     */
    #[Test]
    public function le_total_reste_entier_quelle_que_soit_la_forme_des_entrees(): void
    {
        $this->assertSame(21000, Money::total(['7000', 7000.0, 7000]));
        $this->assertSame(0, Money::total([]));
    }

    #[Test]
    public function il_formate_un_montant_sans_decimale(): void
    {
        $this->assertSame('12 500 XOF', Money::format(12500));
    }
}
