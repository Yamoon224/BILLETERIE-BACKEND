<?php

namespace App\Domains\Payments\Enums;

/**
 * Moyen d'encaissement.
 *
 * Le tableau de bord doit distinguer les encaissements par moyen : c'est un
 * critere de recette explicite du cahier des charges, et surtout la seule
 * facon pour un gestionnaire de rapprocher la caisse physique d'une gare avec
 * ce que la plateforme a enregistre.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case Card = 'card';

    /**
     * L'encaissement passe-t-il par l'agregateur tiers ?
     *
     * Les especes n'y passent pas : elles sont constatees par un agent, pas
     * autorisees par un operateur. La distinction commande tout le cycle de
     * vie du paiement — l'un attend un rappel, l'autre est immediatement
     * definitif.
     */
    public function isDelegated(): bool
    {
        return in_array($this, [self::MobileMoney, self::Card], true);
    }

    /** Cet encaissement compte-t-il dans la caisse physique d'une gare ? */
    public function isCounterCash(): bool
    {
        return $this === self::Cash;
    }

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Especes',
            self::MobileMoney => 'Mobile money',
            self::Card => 'Carte bancaire',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
