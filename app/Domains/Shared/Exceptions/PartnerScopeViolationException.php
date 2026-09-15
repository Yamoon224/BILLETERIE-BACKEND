<?php

namespace App\Domains\Shared\Exceptions;

/**
 * L'appelant a designe une ressource appartenant a un autre partenaire.
 *
 * 403 et non 404, pour la meme raison que CompanyScopeViolationException : la
 * ressource existe, c'est l'acces qui est refuse.
 */
final class PartnerScopeViolationException extends DomainException
{
    public static function make(): self
    {
        return new self(
            'Cette ressource appartient a un autre partenaire.',
            'partner_scope_violation',
            403,
        );
    }
}
