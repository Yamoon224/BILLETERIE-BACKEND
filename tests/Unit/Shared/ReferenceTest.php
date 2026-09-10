<?php

namespace Tests\Unit\Shared;

use App\Domains\Shared\Support\Reference;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReferenceTest extends TestCase
{
    /**
     * L'alphabet exclut les caracteres qui se confondent a l'oeil ou a
     * l'oreille : une reference se dicte au telephone et se relit sur un
     * ticket thermique.
     */
    #[Test]
    public function la_reference_evite_les_caracteres_ambigus(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $suffix = substr(Reference::make('RES'), 4);

            $this->assertSame(
                '',
                preg_replace('/[23456789ABCDEFGHJKLMNPQRSTVWXYZ]/', '', $suffix),
                "La reference {$suffix} contient un caractere ambigu (I, O, U, 0 ou 1).",
            );
        }
    }

    #[Test]
    public function la_reference_porte_son_prefixe_et_sa_longueur(): void
    {
        $reference = Reference::make('BIL', 6);

        $this->assertStringStartsWith('BIL-', $reference);
        $this->assertSame(10, strlen($reference));
    }

    #[Test]
    public function une_reference_datee_expose_sa_date(): void
    {
        $reference = Reference::dated('DEP', new DateTimeImmutable('2026-09-06 07:00:00'));

        $this->assertStringStartsWith('DEP-260906-', $reference);
    }

    /**
     * Deux references tirees a la suite doivent differer : un compteur
     * sequentiel permettrait de reclamer le billet du voyageur suivant.
     */
    #[Test]
    public function deux_references_consecutives_different(): void
    {
        $references = [];

        for ($i = 0; $i < 500; $i++) {
            $references[] = Reference::make('RES');
        }

        $this->assertCount(500, array_unique($references));
    }

    #[Test]
    public function la_normalisation_absorbe_les_ecarts_de_saisie(): void
    {
        $this->assertSame('RES-K7M3XZ', Reference::normalize('  res-k7m 3xz '));
    }
}
