<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Entree du journal d'audit.
 *
 * Etendue pour aligner la cle primaire sur le reste du schema (UUID) et parce
 * que le journal est expose en lecture par l'API : lui donner un modele
 * applicatif evite de faire dependre un controleur d'une classe du paquet.
 *
 * @property string $id
 */
class ActivityLog extends SpatieActivity
{
    use HasUuids;
}
