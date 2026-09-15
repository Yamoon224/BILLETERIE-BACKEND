<?php

namespace Tests\Feature\CarRental;

use App\Models\City;
use App\Models\Partner;
use App\Models\RentalVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RentalVehicleTest extends TestCase
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
    public function un_gestionnaire_ne_liste_que_les_vehicules_de_son_partenaire(): void
    {
        RentalVehicle::factory()->count(2)->create(['partner_id' => $this->mine->id]);
        RentalVehicle::factory()->count(3)->create(['partner_id' => $this->other->id]);

        $manager = $this->userWithRole('partner_manager', null, $this->mine);

        $this->actingAs($manager)->getJson('/api/rental-vehicles')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    public function un_gestionnaire_ne_peut_pas_modifier_le_vehicule_d_un_concurrent(): void
    {
        $vehicle = RentalVehicle::factory()->create(['partner_id' => $this->other->id]);
        $manager = $this->userWithRole('partner_manager', null, $this->mine);

        $this->actingAs($manager)
            ->patchJson("/api/rental-vehicles/{$vehicle->id}", ['brand' => 'Pirate'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'partner_scope_violation');
    }

    /** L'identifiant de partenaire envoye par le client est ecrase par le sien. */
    #[Test]
    public function un_vehicule_cree_par_un_gestionnaire_est_rattache_a_son_partenaire(): void
    {
        $city = City::factory()->create();
        $manager = $this->userWithRole('partner_manager', null, $this->mine);

        $response = $this->actingAs($manager)->postJson('/api/rental-vehicles', [
            'partner_id' => $this->other->id,
            'city_id' => $city->id,
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'category' => 'citadine',
            'transmission' => 'manual',
            'fuel_type' => 'petrol',
            'seats' => 5,
            'price_per_day' => 30000,
        ])->assertCreated();

        $this->assertSame($this->mine->id, $response->json('data.partner_id'));
    }

    #[Test]
    public function la_recherche_publique_ne_retourne_que_les_vehicules_actifs(): void
    {
        RentalVehicle::factory()->count(2)->create(['partner_id' => $this->mine->id, 'is_active' => true]);
        RentalVehicle::factory()->inactive()->create(['partner_id' => $this->mine->id]);

        $this->getJson('/api/rental-vehicles/search')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }
}
