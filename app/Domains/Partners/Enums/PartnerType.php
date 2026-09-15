<?php

namespace App\Domains\Partners\Enums;

/**
 * Catalogue(s) exploite(s) par un partenaire.
 *
 * N'entre dans aucune contrainte : un partenaire de type `housing` n'est pas
 * empeche en base de recevoir un vehicule de location plus tard. Le champ
 * oriente l'ecran de gestion (quel formulaire proposer en premier), il ne
 * verrouille pas une decision commerciale qui peut changer.
 */
enum PartnerType: string
{
    case Housing = 'housing';
    case CarRental = 'car_rental';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Housing => 'Appartements',
            self::CarRental => 'Location auto',
            self::Both => 'Appartements et location auto',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
