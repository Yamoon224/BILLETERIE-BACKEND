<?php

namespace App\Domains\Booking\Enums;

/**
 * Canal par lequel une reservation est entree.
 *
 * Ce n'est pas une simple etiquette statistique : le canal decide du cycle de
 * vie. Une vente en ligne bloque des places puis attend un paiement ; une
 * vente au guichet est encaissee avant d'exister. Une vente hors ligne, elle,
 * est une vente au guichet dont le serveur n'a eu connaissance qu'apres coup —
 * elle porte donc une heure de vente distincte de son heure d'arrivee.
 */
enum BookingChannel: string
{
    case Online = 'online';
    case Counter = 'counter';
    case OfflineCounter = 'offline_counter';

    /** Les places doivent-elles etre bloquees en attendant un paiement ? */
    public function requiresSeatHold(): bool
    {
        return $this === self::Online;
    }

    /** La vente est-elle encaissee au moment ou elle est enregistree ? */
    public function isPaidUpfront(): bool
    {
        return in_array($this, [self::Counter, self::OfflineCounter], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Online => 'En ligne',
            self::Counter => 'Guichet',
            self::OfflineCounter => 'Guichet hors ligne',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
