<?php

namespace App\Domains\Scheduling\Exceptions;

use App\Domains\Scheduling\Enums\TripStatus;
use App\Domains\Shared\Exceptions\DomainException;

/**
 * Le depart ne peut plus etre modifie tel que demande.
 *
 * Trois refus distincts, trois codes applicatifs : l'interface ne propose pas
 * la meme suite selon qu'un depart est termine, deja vendu, ou porteur de
 * reservations. Un code unique obligerait le frontend a lire le message
 * francais pour decider quoi afficher.
 */
final class TripNotEditableException extends DomainException
{
    public static function forStatus(string $reference, TripStatus $status): self
    {
        return new self(
            "Le depart {$reference} n'est plus modifiable ({$status->label()}).",
            'trip_not_editable',
            409,
            ['reference' => $reference, 'status' => $status->value],
        );
    }

    public static function alreadySold(string $reference, int $bookings, string $field): self
    {
        return new self(
            "Le depart {$reference} compte deja {$bookings} reservation(s) : le champ « {$field} » ne peut plus changer.",
            'trip_field_frozen',
            409,
            ['reference' => $reference, 'bookings' => $bookings, 'field' => $field],
        );
    }

    public static function hasBookings(string $reference, int $bookings): self
    {
        return new self(
            "Le depart {$reference} porte {$bookings} reservation(s) et ne peut pas etre supprime. Annulez-le plutot.",
            'trip_has_bookings',
            409,
            ['reference' => $reference, 'bookings' => $bookings],
        );
    }
}
