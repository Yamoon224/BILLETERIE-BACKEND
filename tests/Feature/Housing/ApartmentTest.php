<?php

namespace Tests\Feature\Housing;

use App\Models\Apartment;
use App\Models\City;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApartmentTest extends TestCase
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
    public function un_gestionnaire_ne_liste_que_les_appartements_de_son_partenaire(): void
    {
        Apartment::factory()->count(2)->create(['partner_id' => $this->mine->id]);
        Apartment::factory()->count(3)->create(['partner_id' => $this->other->id]);

        $manager = $this->userWithRole('partner_manager', null, $this->mine);

        $this->actingAs($manager)->getJson('/api/apartments')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    public function un_gestionnaire_ne_peut_pas_modifier_l_appartement_d_un_concurrent(): void
    {
        $apartment = Apartment::factory()->create(['partner_id' => $this->other->id]);
        $manager = $this->userWithRole('partner_manager', null, $this->mine);

        $this->actingAs($manager)
            ->patchJson("/api/apartments/{$apartment->id}", ['title' => 'Pirate'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'partner_scope_violation');
    }

    /** L'identifiant de partenaire envoye par le client est ecrase par le sien. */
    #[Test]
    public function un_appartement_cree_par_un_gestionnaire_est_rattache_a_son_partenaire(): void
    {
        $city = City::factory()->create();
        $manager = $this->userWithRole('partner_manager', null, $this->mine);

        $response = $this->actingAs($manager)->postJson('/api/apartments', [
            'partner_id' => $this->other->id,
            'city_id' => $city->id,
            'title' => 'Studio meuble',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'capacity' => 2,
            'price_per_night' => 20000,
        ])->assertCreated();

        $this->assertSame($this->mine->id, $response->json('data.partner_id'));
    }

    #[Test]
    public function la_recherche_publique_ne_retourne_que_les_appartements_actifs(): void
    {
        Apartment::factory()->count(2)->create(['partner_id' => $this->mine->id, 'is_active' => true]);
        Apartment::factory()->inactive()->create(['partner_id' => $this->mine->id]);

        $this->getJson('/api/apartments/search')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }
}
