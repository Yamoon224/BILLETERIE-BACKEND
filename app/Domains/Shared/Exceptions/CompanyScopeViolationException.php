<?php

namespace App\Domains\Shared\Exceptions;

/**
 * L'appelant a designe une ressource appartenant a une autre compagnie.
 *
 * 403 et non 404 : la ressource existe, c'est l'acces qui est refuse. On
 * pourrait arguer qu'un 404 en dirait moins a un curieux ; mais l'identifiant
 * a bien ete fourni par quelqu'un, et repondre « introuvable » a un
 * gestionnaire qui s'est trompe de fenetre lui ferait chercher un bug qui
 * n'existe pas.
 */
final class CompanyScopeViolationException extends DomainException
{
    public static function make(): self
    {
        return new self(
            'Cette ressource appartient a une autre compagnie.',
            'company_scope_violation',
            403,
        );
    }
}
