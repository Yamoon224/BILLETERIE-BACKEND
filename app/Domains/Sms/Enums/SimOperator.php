<?php

namespace App\Domains\Sms\Enums;

/** Operateur mobile porteur d'une carte SIM du SMS Box. */
enum SimOperator: string
{
    case Orange = 'orange';
    case Mtn = 'mtn';
    case Moov = 'moov';

    public function label(): string
    {
        return match ($this) {
            self::Orange => 'Orange',
            self::Mtn => 'MTN',
            self::Moov => 'Moov',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Operateur porteur d'un numero ivoirien, d'apres son prefixe a deux
     * chiffres (numerotation a dix chiffres depuis 2021).
     *
     * Approximatif par construction : la portabilite du numero peut faire
     * mentir le prefixe. C'est neanmoins la seule information disponible
     * sans interroger un registre d'operateurs tiers, et une file d'envoi qui
     * affiche « operateur inconnu » sur un numero portable plutot que de
     * planter reste un compromis raisonnable.
     */
    public static function fromPhoneNumber(?string $phone): ?self
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Le prefixe pays n'est pas porteur d'information d'operateur : on
        // l'ecarte pour isoler le numero local a dix chiffres.
        if (str_starts_with($digits, '225') && strlen($digits) > 10) {
            $digits = substr($digits, 3);
        }

        $prefix = substr($digits, 0, 2);

        return match ($prefix) {
            '07', '08', '09' => self::Orange,
            '05', '06' => self::Mtn,
            '01', '02', '03' => self::Moov,
            default => null,
        };
    }
}
