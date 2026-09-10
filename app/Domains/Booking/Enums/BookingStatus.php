<?php

namespace App\Domains\Booking\Enums;

/**
 * Cycle de vie d'une reservation.
 *
 *   pending --> confirmed --> refunded
 *      |             |
 *      |             `--> cancelled
 *      `--> expired
 *
 * `pending` et `expired` n'existent que pour le canal en ligne : au guichet,
 * l'encaissement precede l'emission, une vente naissant donc directement
 * `confirmed`.
 *
 * La distinction entre `expired` et `cancelled` n'est pas cosmetique : la
 * premiere est une non-vente (le voyageur n'a jamais paye, rien a rembourser),
 * la seconde un retour sur une vente encaissee. Les confondre fausserait la
 * recette autant que le taux de remplissage.
 */
enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    /** Cette reservation immobilise-t-elle encore des places ? */
    public function holdsSeats(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    /** Cette reservation compte-t-elle dans la recette ? */
    public function isRevenue(): bool
    {
        return $this === self::Confirmed;
    }

    /** Un paiement peut-il encore lui etre rattache ? */
    public function isPayable(): bool
    {
        return $this === self::Pending;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Cancelled, self::Expired, self::Refunded], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de paiement',
            self::Confirmed => 'Confirmee',
            self::Cancelled => 'Annulee',
            self::Expired => 'Expiree',
            self::Refunded => 'Remboursee',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
