<?php

namespace App\Domains\Shared\Support;

use App\Models\User;

/**
 * Perimetre de compagnie d'un appelant.
 *
 * Le cloisonnement entre compagnies partenaires est la regle de securite la
 * plus sensible de la plateforme : une compagnie ne doit jamais voir les
 * ventes, les recettes ni les departs d'une autre. Cette regle est resolue
 * **ici et nulle part ailleurs**, a partir du jeton de l'appelant.
 *
 * Le point essentiel : `company_id` n'est jamais lu dans la requete. Un filtre
 * de securite que le client peut choisir n'est pas un filtre de securite,
 * c'est un parametre d'affichage — et il suffit alors d'un identifiant devine
 * pour consulter la caisse du concurrent.
 *
 * `null` signifie « aucune restriction » et ne concerne que l'administrateur
 * de plateforme. Tout autre compte rattache a une compagnie est borne a la
 * sienne.
 */
final class CompanyScope
{
    private function __construct() {}

    /** Identifiant de compagnie imposé a l'appelant, ou `null` s'il voit tout. */
    public static function forUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        // L'administrateur plateforme supervise l'ensemble : c'est son role.
        if ($user->hasRole('platform_admin')) {
            return null;
        }

        return $user->company_id;
    }

    /**
     * Ajoute la borne de compagnie a un jeu de filtres, en ecrasant toute
     * valeur venue de la requete.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function apply(array $filters, ?User $user): array
    {
        $companyId = self::forUser($user);

        if ($companyId !== null) {
            $filters['company_id'] = $companyId;
        }

        return $filters;
    }

    /** L'appelant a-t-il le droit d'agir sur cette compagnie ? */
    public static function allows(?User $user, ?string $companyId): bool
    {
        $scope = self::forUser($user);

        return $scope === null || $scope === $companyId;
    }
}
