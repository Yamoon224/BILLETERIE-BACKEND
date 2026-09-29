<?php

namespace App\Domains\Promotions\Enums;

/** Emplacement d'affichage d'une promotion sur le site voyageur. */
enum PromotionZone: string
{
    case HeroBanner = 'hero_banner';
    case FeaturedTile = 'featured_tile';

    public function label(): string
    {
        return match ($this) {
            self::HeroBanner => 'Banniere hero (accueil)',
            self::FeaturedTile => 'Tuile « A la une »',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
