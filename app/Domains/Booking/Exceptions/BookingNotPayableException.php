<?php

namespace App\Domains\Booking\Exceptions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Shared\Exceptions\DomainException;

/**
 * Aucun paiement ne peut plus etre rattache a cette reservation.
 *
 * Le cas le plus frequent n'est pas une erreur du client mais l'expiration du
 * blocage : le voyageur a valide son paiement mobile money au moment ou ses
 * places retournaient a la vente. C'est precisement pour cette collision que
 * l'expiration est verifiee avant d'accepter un encaissement — encaisser puis
 * decouvrir la place vendue serait un remboursement de plus, et un voyageur
 * debout dans le couloir.
 */
final class BookingNotPayableException extends DomainException
{
    public static function forStatus(string $reference, BookingStatus $status): self
    {
        return new self(
            "La reservation {$reference} n'attend plus de paiement ({$status->label()}).",
            'booking_not_payable',
            409,
            ['reference' => $reference, 'status' => $status->value],
        );
    }

    public static function holdExpired(string $reference): self
    {
        return new self(
            "Le delai de paiement de la reservation {$reference} est ecoule, les places ont ete remises en vente.",
            'booking_hold_expired',
            409,
            ['reference' => $reference],
        );
    }
}
