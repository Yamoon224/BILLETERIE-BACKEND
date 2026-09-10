<?php

namespace App\Domains\Audit\Contracts;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Journal d'audit, en **lecture seule**.
 *
 * Aucune methode d'ecriture, de modification ni de suppression : les entrees
 * sont produites par les modeles eux-memes (spatie/laravel-activitylog) et un
 * journal qu'on peut modifier depuis l'application ne prouve plus rien. C'est
 * la meme raison qui fait qu'il n'existe aucune route d'ecriture pour ce
 * domaine.
 */
interface AuditLogRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * Valeurs distinctes presentes dans le journal, pour alimenter les filtres.
     *
     * @return array{subject_types: list<string>, events: list<string>}
     */
    public function facets(): array;
}
