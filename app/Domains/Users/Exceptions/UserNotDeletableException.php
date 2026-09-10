<?php

namespace App\Domains\Users\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/** Refus de supprimer un compte. */
final class UserNotDeletableException extends DomainException
{
    public static function self(): self
    {
        return new self(
            'Vous ne pouvez pas supprimer votre propre compte.',
            'cannot_delete_own_account',
            409,
        );
    }

    public static function hasSales(string $name, int $sales): self
    {
        return new self(
            "Le compte « {$name} » a encaisse {$sales} vente(s) et ne peut pas etre supprime. Desactivez-le plutot.",
            'user_has_sales',
            409,
            ['name' => $name, 'sales' => $sales],
        );
    }
}
