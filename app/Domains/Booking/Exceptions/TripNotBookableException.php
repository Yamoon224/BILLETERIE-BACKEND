<?php

namespace App\Domains\Booking\Exceptions;

use App\Domains\Scheduling\Enums\TripStatus;
use App\Domains\Shared\Exceptions\DomainException;

/**
 * Le depart n'accepte plus de reservation : annule, deja parti, ou arrive.
 *
 * Le statut est renvoye dans le contexte parce que l'interface n'en tire pas
 * la meme conclusion selon les cas — un depart annule appelle une proposition
 * de report, un depart deja parti appelle le depart suivant.
 */
final class TripNotBookableException extends DomainException
{
    public static function forStatus(string $reference, TripStatus $status): self
    {
        return new self(
            "Le depart {$reference} n'accepte plus de reservation ({$status->label()}).",
            'trip_not_bookable',
            409,
            ['trip_reference' => $reference, 'status' => $status->value],
        );
    }

    public static function alreadyDeparted(string $reference): self
    {
        return new self(
            "Le depart {$reference} a deja quitte la gare.",
            'trip_already_departed',
            409,
            ['trip_reference' => $reference],
        );
    }
}
