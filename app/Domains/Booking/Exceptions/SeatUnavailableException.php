<?php

namespace App\Domains\Booking\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Une des places demandees vient d'etre prise.
 *
 * 409 et non 422 : la demande etait valide au moment ou elle a ete formee, et
 * le client n'a rien a corriger dans sa saisie — c'est l'etat du monde qui a
 * change entre l'affichage du plan et la validation. Le distinguer d'une
 * erreur de saisie permet a l'interface de rafraichir le plan de salle au lieu
 * de souligner un champ en rouge.
 */
final class SeatUnavailableException extends DomainException
{
    /** @param  list<string>  $seats */
    public static function forSeats(array $seats): self
    {
        $list = implode(', ', $seats);

        return new self(
            count($seats) > 1
                ? "Les places {$list} ne sont plus disponibles."
                : "La place {$list} n'est plus disponible.",
            'seat_unavailable',
            409,
            ['seats' => $seats],
        );
    }
}
