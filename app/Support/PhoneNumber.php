<?php

namespace App\Support;

/**
 * Un numero ivoirien se saisit sous des formes variees (espaces, tirets,
 * indicatif omis ou non) : la connexion par telephone et l'unicite en base ne
 * doivent pas dependre de la ponctuation tapee par l'utilisateur.
 */
final class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // Un numero local (10 chiffres, sans l'indicatif pays) rejoint le
        // format stocke en base, indicatif inclus.
        if (strlen($digits) === 10) {
            $digits = '225'.$digits;
        }

        return '+'.$digits;
    }
}
