<?php

namespace Tests\Feature\Partners;

use App\Models\Apartment;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cloisonnement entre partenaires (appartements, location auto).
 *
 * Symetrique de Network\CompanyScopeTest : un partenaire ne voit ni ne
 * modifie jamais la fiche ou le catalogue d'un autre.
 */
class PartnerScopeTest extends TestCase
{
    use RefreshDatabase;

    private Partner $mine;

    private Partner $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mine = Partner::factory()->create();
        $this->other = Partner::factory()->create();
    }

    #[Test]
    public function un_gestionnaire_de_partenaire_ne_liste_que_son_partenaire(): void
    {
        $manager = $this->userWithRole('partner_manager', null, $this->mine);

        $this->actingAs($manager)->getJson('/api/partners')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->mine->id);
    }

    #[Test]
    public function un_gestionnaire_ne_peut_pas_modifier_la_fiche_d_un_autre_partenaire(): void
    {
        $manager = $this->userWithRole('partner_manager', null, $this->mine);

        $this->actingAs($manager)
            ->patchJson("/api/partners/{$this->other->id}", ['name' => 'Pirate'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'partner_scope_violation');
    }

    #[Test]
    public function seul_l_administrateur_plateforme_cree_un_partenaire(): void
    {
        $manager = $this->userWithRole('partner_manager', null, $this->mine);

        $this->actingAs($manager)
            ->postJson('/api/partners', ['name' => 'Nouvelle agence', 'type' => 'housing'])
            ->assertStatus(403);

        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)
            ->postJson('/api/partners', ['name' => 'Nouvelle agence', 'type' => 'housing'])
            ->assertCreated();
    }

    #[Test]
    public function un_partenaire_avec_des_fiches_publiees_ne_peut_pas_etre_supprime(): void
    {
        Apartment::factory()->create(['partner_id' => $this->mine->id]);
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)
            ->deleteJson("/api/partners/{$this->mine->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'resource_in_use');
    }
}
