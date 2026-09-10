<?php

namespace App\Domains\Booking\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Une place demandee n'existe pas dans ce vehicule, ou revient deux fois dans
 * la meme demande.
 *
 * 422 et non 409, contrairement a [[SeatUnavailableException]] : ici la saisie
 * elle-meme est fautive, et rafraichir le plan de salle n'y changerait rien.
 */
final class InvalidSeatException extends DomainException
{
    /** @param  list<string>  $seats */
    public static function unknown(array $seats): self
    {
        return new self(
            'Places inexistantes sur ce vehicule : '.implode(', ', $seats).'.',
            'invalid_seat',
            422,
            ['seats' => $seats],
        );
    }

    public static function duplicated(string $seat): self
    {
        return new self(
            "La place {$seat} est demandee deux fois dans la meme reservation.",
            'duplicate_seat',
            422,
            ['seat' => $seat],
        );
    }

    public static function countMismatch(int $seats, int $passengers): self
    {
        return new self(
            "Le nombre de places ({$seats}) ne correspond pas au nombre de voyageurs ({$passengers}).",
            'seat_passenger_mismatch',
            422,
            ['seats' => $seats, 'passengers' => $passengers],
        );
    }
}
