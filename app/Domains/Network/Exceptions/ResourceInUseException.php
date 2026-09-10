<?php

namespace App\Domains\Network\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

/**
 * Refus de supprimer une fiche du referentiel encore citee ailleurs.
 *
 * Le refus n'est pas une precaution technique — les cles etrangeres
 * l'imposeraient de toute facon — mais une regle de tracabilite. Un billet
 * emis il y a huit mois doit rester explicable : quelle compagnie l'a vendu,
 * de quelle gare il partait, sur quel vehicule. Effacer la fiche rendrait le
 * billet illisible sans rien effacer de sa realite.
 *
 * L'alternative offerte est toujours la meme et figure dans le message :
 * desactiver plutot que supprimer. Une fiche desactivee disparait des
 * selecteurs sans disparaitre de l'histoire.
 *
 * Une classe unique plutot qu'une par entite : le refus est le meme, seul le
 * nom change. Cinq classes identiques auraient surtout garanti que la sixieme
 * serait ecrite differemment.
 */
final class ResourceInUseException extends DomainException
{
    public static function make(string $resource, string $label, int $dependents): self
    {
        return new self(
            "{$resource} « {$label} » est utilise par {$dependents} element(s) et ne peut pas etre supprime. Desactivez-le plutot.",
            'resource_in_use',
            409,
            ['resource' => $resource, 'label' => $label, 'dependents' => $dependents],
        );
    }
}
