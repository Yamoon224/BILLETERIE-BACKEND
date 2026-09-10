<?php

namespace App\Domains\Ticketing\Enums;

/**
 * Etat d'un billet.
 *
 *   issued --> used
 *      `--> cancelled / refunded
 *
 * `used` est pose par le premier scan reussi et n'en sort jamais : c'est cet
 * etat, et lui seul, qui fait refuser la seconde presentation du meme billet.
 */
enum TicketStatus: string
{
    case Issued = 'issued';
    case Used = 'used';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /** Ce billet occupe-t-il encore sa place dans le bus ? */
    public function occupiesSeat(): bool
    {
        return in_array($this, [self::Issued, self::Used], true);
    }

    /** Ce billet peut-il encore etre presente a l'embarquement ? */
    public function isBoardable(): bool
    {
        return $this === self::Issued;
    }

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Emis',
            self::Used => 'Utilise',
            self::Cancelled => 'Annule',
            self::Refunded => 'Rembourse',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
