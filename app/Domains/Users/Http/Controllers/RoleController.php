<?php

namespace App\Domains\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

/**
 * Roles assignables et ce qu'ils autorisent.
 *
 * En lecture seule : les roles et leurs permissions sont definis par un seeder
 * versionne, pas modifies en production. Une matrice de droits qu'on peut
 * editer a chaud est une matrice dont personne ne sait plus dire, six mois
 * plus tard, pourquoi elle est dans cet etat — et la separation des taches
 * qu'elle porte devient invérifiable.
 */
class RoleController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ])
            ->values()
            ->all();

        return response()->json(['data' => $roles]);
    }
}
