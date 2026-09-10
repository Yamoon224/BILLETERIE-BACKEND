<?php

namespace Tests;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cree un compte porteur d'un role, avec sa compagnie si le role en exige
     * une.
     *
     * Les roles viennent du seeder versionne et non d'une matrice inventee
     * pour les tests : un test qui s'appuierait sur des permissions fabriquees
     * a la main passerait avec une matrice de production differente, ce qui
     * est exactement le contraire de ce qu'on lui demande.
     */
    protected function userWithRole(string $role, ?Company $company = null): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'company_id' => $company?->id,
        ]);

        $user->assignRole($role);

        return $user->refresh();
    }
}
