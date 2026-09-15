<?php

namespace App\Domains\Partners\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Refus de supprimer un partenaire encore proprietaire de fiches.
 *
 * Meme logique que Network\Exceptions\ResourceInUseException : desactiver
 * conserve la tracabilite des appartements et vehicules deja publies, quand
 * supprimer la casserait.
 */
final class ResourceInUseException extends DomainException
{
    public static function make(string $label, int $dependents): self
    {
        return new self(
            "Le partenaire « {$label} » possede encore {$dependents} fiche(s) et ne peut pas etre supprime. Desactivez-le plutot.",
            'resource_in_use',
            409,
            ['resource' => 'Le partenaire', 'label' => $label, 'dependents' => $dependents],
        );
    }
}
