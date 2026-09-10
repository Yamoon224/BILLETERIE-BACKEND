<?php

namespace App\Domains\Scheduling\Enums;

/**
 * Cycle de vie d'un depart.
 *
 * Les transitions ne sont pas decoratives : elles decident de deux choses que
 * le reste du domaine interroge sans avoir a connaitre le detail — un depart
 * accepte-t-il encore des ventes, et accepte-t-il encore des embarquements ?
 *
 *   scheduled --> boarding --> departed --> arrived
 *        \            \
 *         `--> cancelled <-'
 */
enum TripStatus: string
{
    case Scheduled = 'scheduled';
    case Boarding = 'boarding';
    case Departed = 'departed';
    case Arrived = 'arrived';
    case Cancelled = 'cancelled';

    /**
     * Ce depart accepte-t-il encore des reservations ?
     *
     * L'embarquement reste ouvert a la vente : c'est precisement le moment ou
     * le guichet ecoule les dernieres places devant le bus, et le lui interdire
     * ferait perdre les sieges vides d'un depart deja quai.
     */
    public function acceptsBookings(): bool
    {
        return in_array($this, [self::Scheduled, self::Boarding], true);
    }

    /** Ce depart accepte-t-il encore un scan de billet a l'embarquement ? */
    public function acceptsBoarding(): bool
    {
        return in_array($this, [self::Scheduled, self::Boarding], true);
    }

    /** Etat terminal : plus aucune transition n'en sort. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Arrived, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programme',
            self::Boarding => 'Embarquement',
            self::Departed => 'Parti',
            self::Arrived => 'Arrive',
            self::Cancelled => 'Annule',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
