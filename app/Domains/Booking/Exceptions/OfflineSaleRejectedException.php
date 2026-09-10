<?php

namespace App\Domains\Booking\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Une vente hors ligne remontee par un agent est refusee.
 *
 * Le refus est volontairement bruyant. Une vente au guichet a deja eu lieu
 * dans le monde reel : de l'argent a change de main, un ticket a ete imprime,
 * un voyageur est peut-etre deja assis. La faire disparaitre silencieusement
 * parce que le serveur ne peut pas l'enregistrer creerait un ecart de caisse
 * que personne ne verrait avant le decompte du soir. Chaque refus est donc
 * rendu a la tablette avec son motif, pour etre traite par un responsable.
 */
final class OfflineSaleRejectedException extends DomainException
{
    public static function tooOld(string $clientReference, int $maxAgeHours): self
    {
        return new self(
            "Vente hors ligne trop ancienne pour etre enregistree automatiquement (plus de {$maxAgeHours} h).",
            'offline_sale_too_old',
            422,
            ['client_reference' => $clientReference, 'max_age_hours' => $maxAgeHours],
        );
    }

    public static function inFuture(string $clientReference): self
    {
        return new self(
            'Vente hors ligne datee dans le futur : verifiez l horloge de la tablette.',
            'offline_sale_in_future',
            422,
            ['client_reference' => $clientReference],
        );
    }
}
