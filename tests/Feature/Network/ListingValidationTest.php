<?php

namespace Tests\Feature\Network;

use App\Models\Apartment;
use App\Models\Company;
use App\Models\RentalVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Validation ou rejet, par l'administrateur de plateforme, d'une compagnie ou
 * d'une fiche partenaire en attente.
 *
 * L'autorite de validation n'appartient qu'a l'administrateur : ni un
 * gestionnaire de compagnie ni un gestionnaire de partenaire ne peuvent
 * s'auto-valider, quel que soit le pilier concerne.
 */
class ListingValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seul_l_administrateur_valide_une_compagnie_en_attente(): void
    {
        $company = Company::factory()->pending()->create();
        $manager = $this->userWithRole('company_manager', $company);

        $this->actingAs($manager)
            ->postJson("/api/companies/{$company->id}/status", ['status' => 'active'])
            ->assertStatus(403);

        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)
            ->postJson("/api/companies/{$company->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    #[Test]
    public function une_compagnie_peut_etre_rejetee(): void
    {
        $company = Company::factory()->pending()->create();
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)
            ->postJson("/api/companies/{$company->id}/status", ['status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    #[Test]
    public function le_statut_ne_peut_pas_etre_renvoye_en_attente(): void
    {
        $company = Company::factory()->pending()->create();
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)
            ->postJson("/api/companies/{$company->id}/status", ['status' => 'pending'])
            ->assertStatus(422);
    }

    #[Test]
    public function un_appartement_en_attente_peut_etre_valide_par_l_administrateur(): void
    {
        $apartment = Apartment::factory()->pending()->create();
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)
            ->postJson("/api/apartments/{$apartment->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    #[Test]
    public function un_gestionnaire_de_partenaire_ne_peut_pas_valider_son_propre_vehicule(): void
    {
        $vehicle = RentalVehicle::factory()->pending()->create();
        $manager = $this->userWithRole('partner_manager', null, $vehicle->partner);

        $this->actingAs($manager)
            ->postJson("/api/rental-vehicles/{$vehicle->id}/status", ['status' => 'active'])
            ->assertStatus(403);
    }
}
