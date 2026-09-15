<?php

namespace App\Domains\Housing\Enums;

/**
 * Equipements courants d'un appartement meuble en Cote d'Ivoire.
 *
 * Liste fermee plutot que texte libre : elle alimente un filtre de recherche
 * facette par facette, ce qu'un champ libre interdirait (« Piscine » et
 * « piscine privee » ne se filtreraient jamais ensemble).
 */
enum ApartmentAmenity: string
{
    case Wifi = 'wifi';
    case AirConditioning = 'climatisation';
    case Pool = 'piscine';
    case Parking = 'parking';
    case Generator = 'groupe_electrogene';
    case EquippedKitchen = 'cuisine_equipee';
    case SeaView = 'vue_mer';
    case Breakfast = 'petit_dejeuner';
    case Laundry = 'linge_de_maison';
    case Television = 'television';
    case SecurityGuard = 'gardiennage';

    public function label(): string
    {
        return match ($this) {
            self::Wifi => 'Wifi',
            self::AirConditioning => 'Climatisation',
            self::Pool => 'Piscine',
            self::Parking => 'Parking',
            self::Generator => 'Groupe electrogene',
            self::EquippedKitchen => 'Cuisine equipee',
            self::SeaView => 'Vue mer',
            self::Breakfast => 'Petit dejeuner',
            self::Laundry => 'Linge de maison',
            self::Television => 'Television',
            self::SecurityGuard => 'Gardiennage',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
