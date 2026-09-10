<?php

namespace App\Domains\Payments\Enums;

/**
 * Etat d'un encaissement.
 *
 *   pending --> succeeded --> refunded
 *      |
 *      +--> failed
 *      `--> expired
 *
 * `expired` existe separement de `failed` parce que l'agregateur ne repond pas
 * toujours : un paiement mobile money dont le rappel n'est jamais arrive n'a
 * pas echoue, il est resté sans nouvelle. Les confondre ferait declarer un
 * echec la ou le voyageur a peut-etre ete debite — c'est exactement le cas
 * qu'un support client doit pouvoir isoler.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function isSettled(): bool
    {
        return $this === self::Succeeded;
    }

    /** Un rappel de l'agregateur peut-il encore faire changer cet etat ? */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Expired, self::Refunded], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Succeeded => 'Encaisse',
            self::Failed => 'Echoue',
            self::Expired => 'Sans reponse',
            self::Refunded => 'Rembourse',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
