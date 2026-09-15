<?php

namespace App\Domains\Shared\Support;

use App\Models\User;

/**
 * Perimetre de partenaire d'un appelant (appartements, location auto).
 *
 * Symetrique de CompanyScope, pour le meme motif : un partenaire ne doit
 * jamais voir ni modifier les fiches d'un autre, et la borne est resolue ici,
 * a partir du jeton, jamais lue dans la requete.
 *
 * `null` signifie « aucune restriction » et ne concerne que l'administrateur
 * de plateforme. Tout autre compte rattache a un partenaire est borne au sien.
 */
final class PartnerScope
{
    private function __construct() {}

    /** Identifiant de partenaire imposé a l'appelant, ou `null` s'il voit tout. */
    public static function forUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user->hasRole('platform_admin')) {
            return null;
        }

        return $user->partner_id;
    }

    /**
     * Ajoute la borne de partenaire a un jeu de filtres, en ecrasant toute
     * valeur venue de la requete.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function apply(array $filters, ?User $user): array
    {
        $partnerId = self::forUser($user);

        if ($partnerId !== null) {
            $filters['partner_id'] = $partnerId;
        }

        return $filters;
    }

    /** L'appelant a-t-il le droit d'agir sur ce partenaire ? */
    public static function allows(?User $user, ?string $partnerId): bool
    {
        $scope = self::forUser($user);

        return $scope === null || $scope === $partnerId;
    }
}
