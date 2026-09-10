<?php

namespace App\Domains\Shared\Support;

use DateTimeInterface;

/**
 * References publiques : reservations, billets, departs, encaissements.
 *
 * Une reference doit se dicter au telephone et se relire sur un ticket
 * thermique imprime en 203 dpi. D'ou l'alphabet retenu :
 *
 *  - pas de minuscules, qui se brouillent a l'impression thermique ;
 *  - ni I, ni O, ni 0, ni 1, ni U — les quatre premiers se confondent entre
 *    eux a l'oeil, le dernier s'entend « you » au telephone.
 *
 * Les 31 caracteres restants donnent, sur six positions, plus de 800 millions
 * de combinaisons : assez pour qu'une reference ne se deduise pas d'une autre,
 * ce qu'un compteur sequentiel ne garantirait pas. Un voyageur ne doit pas
 * pouvoir reclamer le billet du suivant en incrementant le sien.
 */
final class Reference
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTVWXYZ';

    private function __construct() {}

    /**
     * @param  string  $prefix  court identifiant de nature (RES, BIL, PAY)
     */
    public static function make(string $prefix, int $length = 6): string
    {
        return strtoupper($prefix).'-'.self::randomPart($length);
    }

    /** Reference datee : la date se lit sans requete (DEP-260906-K7M3XZ). */
    public static function dated(string $prefix, DateTimeInterface $date, int $length = 6): string
    {
        return strtoupper($prefix).'-'.$date->format('ymd').'-'.self::randomPart($length);
    }

    private static function randomPart(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            // random_int et non rand() : une reference de billet devinable
            // permettrait de se presenter a l'embarquement avec celle d'un
            // autre voyageur.
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * Normalise une reference saisie a la main.
     *
     * Le guichet la retape sous la dictee : espaces parasites, minuscules et
     * tirets manquants sont la regle, pas l'exception. Refuser une reference
     * correcte pour une espace de trop ferait recommencer la saisie devant une
     * file d'attente.
     */
    public static function normalize(string $value): string
    {
        return strtoupper(str_replace([' ', "\t"], '', trim($value)));
    }
}
