<?php

namespace App\Domains\Booking\Exceptions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Shared\Exceptions\DomainException;

/**
 * La reservation ne peut plus etre annulee.
 *
 * Deux cas se cachent derriere ce refus, et ils n'ont pas la meme reponse : une
 * reservation deja annulee (rien a faire), et une reservation dont au moins un
 * billet a deja embarque (le voyageur est dans le bus). Le code applicatif les
 * distingue pour que le guichet ne propose pas un remboursement impossible.
 */
final class BookingNotCancellableException extends DomainException
{
    public static function forStatus(string $reference, BookingStatus $status): self
    {
        return new self(
            "La reservation {$reference} n'est plus annulable ({$status->label()}).",
            'booking_not_cancellable',
            409,
            ['reference' => $reference, 'status' => $status->value],
        );
    }

    public static function alreadyBoarded(string $reference): self
    {
        return new self(
            "La reservation {$reference} comporte un billet deja utilise a l'embarquement.",
            'booking_already_boarded',
            409,
            ['reference' => $reference],
        );
    }
}
