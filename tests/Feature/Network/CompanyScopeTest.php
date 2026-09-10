<?php

namespace Tests\Feature\Network;

use App\Models\Booking;
use App\Models\City;
use App\Models\Company;
use App\Models\Itinerary;
use App\Models\Station;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cloisonnement entre compagnies partenaires.
 *
 * C'est la regle de securite la plus sensible de la plateforme : une compagnie
 * ne voit ni ne modifie jamais le parc, les departs ou les ventes d'une autre.
 */
class CompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    private Company $mine;

    private Company $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mine = Company::factory()->create();
        $this->other = Company::factory()->create();
    }

    #[Test]
    public function un_gestionnaire_ne_liste_que_les_vehicules_de_sa_compagnie(): void
    {
        Vehicle::factory()->count(2)->create(['company_id' => $this->mine->id]);
        Vehicle::factory()->count(3)->create(['company_id' => $this->other->id]);

        $manager = $this->userWithRole('company_manager', $this->mine);

        $this->actingAs($manager)->getJson('/api/vehicles')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    public function un_gestionnaire_ne_peut_pas_modifier_le_vehicule_d_un_concurrent(): void
    {
        $vehicle = Vehicle::factory()->create(['company_id' => $this->other->id]);
        $manager = $this->userWithRole('company_manager', $this->mine);

        $this->actingAs($manager)
            ->patchJson("/api/vehicles/{$vehicle->id}", ['model' => 'Pirate'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'company_scope_violation');
    }

    /** L'identifiant de compagnie envoye par le client est ecrase par le sien. */
    #[Test]
    public function un_vehicule_cree_par_un_gestionnaire_est_rattache_a_sa_compagnie(): void
    {
        $manager = $this->userWithRole('company_manager', $this->mine);

        $response = $this->actingAs($manager)->postJson('/api/vehicles', [
            'company_id' => $this->other->id,
            'registration' => '1234AB-01',
            'class' => 'standard',
            'seat_capacity' => 50,
            'seats_per_row' => 4,
        ])->assertCreated();

        $this->assertSame($this->mine->id, $response->json('data.company_id'));
    }

    /**
     * Une gare routiere partagee se consulte par toutes les compagnies qui en
     * partent, mais aucune ne la modifie.
     */
    #[Test]
    public function une_gare_partagee_est_consultable_mais_pas_modifiable_par_une_compagnie(): void
    {
        $station = Station::factory()->create(['company_id' => null]);
        $manager = $this->userWithRole('company_manager', $this->mine);

        $this->actingAs($manager)->getJson("/api/stations/{$station->id}")->assertOk();

        $this->actingAs($manager)
            ->patchJson("/api/stations/{$station->id}", ['name' => 'Renommee'])
            ->assertStatus(403);
    }

    #[Test]
    public function un_depart_ne_peut_pas_utiliser_le_vehicule_d_une_autre_compagnie(): void
    {
        $itinerary = Itinerary::factory()->create(['company_id' => $this->mine->id]);
        $foreignVehicle = Vehicle::factory()->create(['company_id' => $this->other->id]);
        $departure = Station::factory()->create();
        $arrival = Station::factory()->create();

        $manager = $this->userWithRole('company_manager', $this->mine);

        $this->actingAs($manager)->postJson('/api/trips', [
            'itinerary_id' => $itinerary->id,
            'vehicle_id' => $foreignVehicle->id,
            'departure_station_id' => $departure->id,
            'arrival_station_id' => $arrival->id,
            'departs_at' => Carbon::now()->addDays(2)->toIso8601String(),
        ])->assertStatus(403);
    }

    #[Test]
    public function un_depart_programme_fige_le_tarif_et_la_capacite(): void
    {
        $itinerary = Itinerary::factory()->create(['company_id' => $this->mine->id, 'base_price' => 9000]);
        $vehicle = Vehicle::factory()->withCapacity(40, 4)->create(['company_id' => $this->mine->id]);
        $departure = Station::factory()->create();
        $arrival = Station::factory()->create();

        $manager = $this->userWithRole('company_manager', $this->mine);

        $tripId = $this->actingAs($manager)->postJson('/api/trips', [
            'itinerary_id' => $itinerary->id,
            'vehicle_id' => $vehicle->id,
            'departure_station_id' => $departure->id,
            'arrival_station_id' => $arrival->id,
            'departs_at' => Carbon::now()->addDays(2)->toIso8601String(),
        ])->assertCreated()->json('data.id');

        // Le tarif et le bus changent ensuite a la source...
        $itinerary->update(['base_price' => 12000]);
        $vehicle->update(['seat_capacity' => 20]);

        // ... le depart garde ce qu'il vend.
        $trip = Trip::findOrFail($tripId);
        $this->assertSame(9000, $trip->price);
        $this->assertSame(40, $trip->seat_capacity);
    }

    #[Test]
    public function le_tarif_d_un_depart_deja_vendu_ne_peut_plus_changer(): void
    {
        $itinerary = Itinerary::factory()->create(['company_id' => $this->mine->id]);
        $trip = Trip::factory()->create(['itinerary_id' => $itinerary->id, 'company_id' => $this->mine->id]);
        Booking::factory()->create(['trip_id' => $trip->id]);

        $manager = $this->userWithRole('company_manager', $this->mine);

        $this->actingAs($manager)
            ->patchJson("/api/trips/{$trip->id}", ['price' => 1000])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'trip_field_frozen');
    }

    #[Test]
    public function une_ville_utilisee_ne_peut_pas_etre_supprimee(): void
    {
        $city = City::factory()->create();
        Station::factory()->create(['city_id' => $city->id]);

        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)
            ->deleteJson("/api/cities/{$city->slug}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'resource_in_use');
    }

    #[Test]
    public function seul_l_administrateur_plateforme_cree_une_compagnie(): void
    {
        $manager = $this->userWithRole('company_manager', $this->mine);

        $this->actingAs($manager)
            ->postJson('/api/companies', ['code' => 'NEW', 'name' => 'Nouvelle compagnie'])
            ->assertStatus(403);

        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)
            ->postJson('/api/companies', ['code' => 'NEW', 'name' => 'Nouvelle compagnie'])
            ->assertCreated();
    }

    #[Test]
    public function un_gestionnaire_ne_peut_pas_attribuer_le_role_administrateur(): void
    {
        $manager = $this->userWithRole('company_manager', $this->mine);

        $this->actingAs($manager)->postJson('/api/users', [
            'name' => 'Escalade',
            'email' => 'escalade@example.test',
            'password' => 'motdepasse-solide',
            'roles' => ['platform_admin'],
        ])->assertStatus(403);
    }
}
