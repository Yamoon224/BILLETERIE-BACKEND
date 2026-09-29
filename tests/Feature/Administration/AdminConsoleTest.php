<?php

namespace Tests\Feature\Administration;

use App\Models\Booking;
use App\Models\Company;
use App\Models\Promotion;
use App\Models\SimCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Console de l'administrateur de plateforme : SMS Box, offres et finances.
 *
 * Les trois ecrans sont transverses aux compagnies et aux partenaires : aucun
 * role autre que `platform_admin` n'y a acces, contrairement au reste du
 * back-office qui se partage entre plusieurs metiers.
 */
class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_gestionnaire_de_compagnie_n_accede_pas_au_sms_box(): void
    {
        $manager = $this->userWithRole('company_manager', Company::factory()->create());

        $this->actingAs($manager)->getJson('/api/sms/overview')->assertStatus(403);
    }

    #[Test]
    public function l_administrateur_consulte_le_sms_box(): void
    {
        SimCard::factory()->create(['operator' => 'orange', 'balance' => 7200]);
        SimCard::factory()->lowBalance()->create(['operator' => 'mtn']);

        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->getJson('/api/sms/overview')
            ->assertOk()
            ->assertJsonCount(2, 'data.sim_cards')
            ->assertJsonPath('data.stats.queued', 0);
    }

    #[Test]
    public function l_administrateur_recharge_une_carte_sim(): void
    {
        $sim = SimCard::factory()->lowBalance()->create();
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)
            ->patchJson("/api/sms/sim-cards/{$sim->id}", ['balance' => 10000])
            ->assertOk()
            ->assertJsonPath('data.is_low_balance', false);
    }

    #[Test]
    public function seul_l_administrateur_publie_une_promotion(): void
    {
        $manager = $this->userWithRole('company_manager', Company::factory()->create());

        $this->actingAs($manager)->postJson('/api/promotions', [
            'title' => 'Offre non autorisee',
            'zone' => 'featured_tile',
        ])->assertStatus(403);

        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->postJson('/api/promotions', [
            'title' => '-15 % sur les residences a Assinie',
            'zone' => 'featured_tile',
            'kind' => 'advertisement',
            'advertiser_name' => 'Residences Assinie SARL',
        ])->assertCreated()->assertJsonPath('data.kind', 'advertisement');
    }

    #[Test]
    public function une_promotion_suspendue_le_reste_apres_relecture(): void
    {
        $promotion = Promotion::factory()->create(['is_active' => true]);
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)
            ->patchJson("/api/promotions/{$promotion->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($promotion->refresh()->is_active);
    }

    #[Test]
    public function un_gestionnaire_de_compagnie_n_accede_pas_aux_finances_de_la_plateforme(): void
    {
        $manager = $this->userWithRole('company_manager', Company::factory()->create());

        $this->actingAs($manager)->getJson('/api/finances')->assertStatus(403);
    }

    #[Test]
    public function les_finances_agregent_la_commission_par_compagnie(): void
    {
        $company = Company::factory()->create(['commission_per_mille' => 100]);
        Booking::factory()->create([
            'company_id' => $company->id,
            'status' => 'confirmed',
            'total_amount' => 10000,
            'commission_amount' => 1000,
        ]);

        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->getJson('/api/finances')
            ->assertOk()
            ->assertJsonPath('data.commission_by_company.0.company_id', $company->id)
            ->assertJsonPath('data.commission_by_company.0.commission', 1000);
    }
}
