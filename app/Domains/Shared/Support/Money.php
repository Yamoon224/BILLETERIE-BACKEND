<?php

namespace App\Domains\Shared\Support;

use InvalidArgumentException;

/**
 * Montants en francs CFA.
 *
 * Le XOF n'a pas de sous-unite en circulation : le plus petit montant
 * manipulable est le franc. Les montants sont donc des **entiers**, partout —
 * en base, dans les DTO, dans les reponses JSON. Un flottant introduirait des
 * centimes qui n'existent pas au guichet, et la recette d'une journee finirait
 * par ne plus tomber juste a un franc pres.
 *
 * Cette classe ne « porte » pas un montant : elle rassemble les operations qui
 * doivent se comporter de la meme facon partout, notamment l'arrondi de la
 * commission — le seul endroit du domaine ou une division intervient.
 */
final class Money
{
    private function __construct() {}

    /**
     * Commission plateforme, en francs entiers.
     *
     * L'arrondi est **au plus proche**, pas tronque : tronquer ferait perdre
     * systematiquement moins d'un franc a la plateforme sur chaque vente, ce
     * qui se voit sur plusieurs milliers de billets par jour et ne se defend
     * dans aucun des deux sens.
     *
     * @param  int  $amount  assiette, en francs
     * @param  int  $perMille  taux en pour mille (25 = 2,5 %)
     */
    public static function commission(int $amount, int $perMille): int
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Un montant negatif ne peut pas porter de commission.');
        }

        if ($perMille < 0 || $perMille > 1000) {
            throw new InvalidArgumentException('Un taux de commission se situe entre 0 et 1000 pour mille.');
        }

        return (int) round($amount * $perMille / 1000);
    }

    /**
     * Somme d'une liste de montants.
     *
     * Existe pour le typage : une agregation SQL revient en chaine ou en
     * flottant selon le pilote, et le type de retour entier est ici la
     * garantie qu'aucune decimale ne s'est glissee dans un cumul de recettes.
     *
     * @param  iterable<int|float|string>  $amounts
     */
    public static function total(iterable $amounts): int
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $total += (int) $amount;
        }

        return $total;
    }

    /** Formatage lisible cote serveur : journaux, SMS, tickets imprimes. */
    public static function format(int $amount, string $currency = 'XOF'): string
    {
        return number_format($amount, 0, ',', ' ').' '.$currency;
    }
}
