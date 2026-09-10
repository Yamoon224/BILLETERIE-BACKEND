<?php

namespace App\Domains\Network\Enums;

/**
 * Categorie de confort d'un vehicule.
 *
 * Elle n'entre dans aucun calcul : le prix est porte par l'itineraire, pas par
 * la classe. Elle existe pour que le voyageur sache ce qu'il achete — un
 * « Abidjan-Korhogo a 9 000 F » ne veut pas dire la meme chose selon qu'il se
 * fait en car climatise ou en minibus.
 */
enum VehicleClass: string
{
    case Standard = 'standard';
    case Comfort = 'comfort';
    case Vip = 'vip';
    case Minibus = 'minibus';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Comfort => 'Confort',
            self::Vip => 'VIP',
            self::Minibus => 'Minibus',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
