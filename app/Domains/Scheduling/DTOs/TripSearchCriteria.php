<?php

namespace App\Domains\Scheduling\DTOs;

use Illuminate\Support\Carbon;

/**
 * Criteres d'une recherche voyageur.
 *
 * Les villes sont designees par leur slug et non par leur identifiant : une
 * recherche se partage par messagerie (« regarde, il y a un bus a 14 h »), et
 * un lien doit rester lisible et retapable. L'objet porte donc exactement ce
 * qu'une URL publique transporte.
 */
final readonly class TripSearchCriteria
{
    public function __construct(
        public string $originSlug,
        public string $destinationSlug,
        public Carbon $date,
        public int $passengers = 1,
        public ?string $companyId = null,
        /** Masque les departs deja complets. */
        public bool $onlyAvailable = false,
        public int $limit = 50,
    ) {}

    /**
     * Borne basse de la recherche.
     *
     * Un depart du jour deja passe n'a pas a figurer dans les resultats : le
     * voyageur qui cherche « aujourd'hui » a 15 h ne peut pas prendre celui de
     * 6 h. Pour toute autre date, la journee entiere est pertinente.
     */
    public function from(?Carbon $now = null): Carbon
    {
        $now ??= Carbon::now();
        $startOfDay = $this->date->copy()->startOfDay();

        return $startOfDay->isSameDay($now) && $now->greaterThan($startOfDay) ? $now : $startOfDay;
    }

    public function to(): Carbon
    {
        return $this->date->copy()->endOfDay();
    }
}
