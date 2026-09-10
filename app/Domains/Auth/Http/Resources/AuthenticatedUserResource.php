<?php

namespace App\Domains\Auth\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Le compte tel que l'appelant se voit lui-meme.
 *
 * Les permissions sont servies a plat plutot que deduites des roles cote
 * client : le frontend n'a pas a connaitre la composition des roles, et un
 * ajustement de droits doit prendre effet sans redeploiement du frontend.
 *
 * Elles servent uniquement a masquer ce qui serait refuse — l'autorisation
 * reelle reste appliquee sur chaque route.
 *
 * @mixin User
 */
class AuthenticatedUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'company_id' => $this->company_id,
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
