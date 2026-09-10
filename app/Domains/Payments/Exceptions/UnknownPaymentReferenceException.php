<?php

namespace App\Domains\Payments\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Rappel portant une reference que nous n'avons jamais emise.
 *
 * 404 et non 422 : la requete est bien formee, c'est la ressource designee qui
 * n'existe pas. La distinction compte pour l'agregateur, dont les politiques
 * de reessai different selon le code — un 422 le ferait retenter indefiniment
 * un rappel qui n'aboutira jamais.
 */
final class UnknownPaymentReferenceException extends DomainException
{
    public static function make(string $externalReference): self
    {
        return new self(
            'Aucun encaissement ne correspond a cette reference.',
            'unknown_payment_reference',
            404,
            ['external_reference' => $externalReference],
        );
    }
}
