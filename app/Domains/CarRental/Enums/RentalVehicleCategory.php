<?php

namespace App\Domains\CarRental\Enums;

/**
 * Categorie commerciale d'un vehicule de location.
 *
 * Distincte de App\Domains\Network\Enums\VehicleClass : celle-ci decrit le
 * confort d'un car interurbain, celle-ci le gabarit d'un vehicule loue a la
 * journee. Les deux vocabulaires divergent des le depart (un « minibus » de
 * location n'a pas la meme capacite qu'un minibus de transport interurbain).
 */
enum RentalVehicleCategory: string
{
    case Citadine = 'citadine';
    case Berline = 'berline';
    case Suv = 'suv';
    case Minibus = 'minibus';
    case Luxe = 'luxe';
    case Utilitaire = 'utilitaire';

    public function label(): string
    {
        return match ($this) {
            self::Citadine => 'Citadine',
            self::Berline => 'Berline',
            self::Suv => '4x4 / SUV',
            self::Minibus => 'Minibus',
            self::Luxe => 'Luxe',
            self::Utilitaire => 'Utilitaire',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
