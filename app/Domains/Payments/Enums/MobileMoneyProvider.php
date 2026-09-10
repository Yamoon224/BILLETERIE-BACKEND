<?php

namespace App\Domains\Payments\Enums;

/**
 * Operateurs mobile money proposes au voyageur.
 *
 * L'application ne se connecte a aucun d'eux : le cahier des charges impose de
 * passer par un agregateur unique. Cette enumeration ne sert donc pas a
 * router un appel, mais a deux choses concretes — afficher le bon logo au
 * moment du choix, et transmettre a l'agregateur le canal que le voyageur a
 * designe, pour lui eviter un ecran de selection de plus sur un reseau lent.
 */
enum MobileMoneyProvider: string
{
    case OrangeMoney = 'orange_money';
    case MtnMoney = 'mtn_money';
    case MoovMoney = 'moov_money';
    case Wave = 'wave';

    public function label(): string
    {
        return match ($this) {
            self::OrangeMoney => 'Orange Money',
            self::MtnMoney => 'MTN MoMo',
            self::MoovMoney => 'Moov Money',
            self::Wave => 'Wave',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
