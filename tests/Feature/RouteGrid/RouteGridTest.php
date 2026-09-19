<?php

namespace Tests\Feature\RouteGrid;

use App\Models\City;
use App\Models\Company;
use App\Models\RouteGridEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteGridTest extends TestCase
{
    use RefreshDatabase;

    private City $abidjan;

    private City $bouake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->abidjan = City::factory()->named('Abidjan', 'abidjan')->create();
        $this->bouake = City::factory()->named('Bouake', 'bouake')->create();
    }

    #[Test]
    public function la_recherche_publique_ne_retourne_que_les_lignes_actives_de_la_liaison(): void
    {
        $other = City::factory()->create();

        RouteGridEntry::factory()->create([
            'origin_city_id' => $this->abidjan->id, 'destination_city_id' => $this->bouake->id,
            'company_name' => 'Chere', 'price' => 8000,
        ]);
        RouteGridEntry::factory()->create([
            'origin_city_id' => $this->abidjan->id, 'destination_city_id' => $this->bouake->id,
            'company_name' => 'Economique', 'price' => 6000,
        ]);
        RouteGridEntry::factory()->inactive()->create([
            'origin_city_id' => $this->abidjan->id, 'destination_city_id' => $this->bouake->id,
        ]);
        RouteGridEntry::factory()->create([
            'origin_city_id' => $this->abidjan->id, 'destination_city_id' => $other->id,
        ]);

        $response = $this->getJson('/api/route-grid/search?origin=abidjan&destination=bouake')->assertOk();

        $response->assertJsonCount(2, 'data');
        // De la moins chere a la plus chere.
        $this->assertSame(['Economique', 'Chere'], array_column($response->json('data'), 'company_name'));
        $response->assertJsonPath('data.0.origin_city.slug', 'abidjan');
    }

    #[Test]
    public function la_recherche_publique_exige_deux_villes_distinctes_et_connues(): void
    {
        $this->getJson('/api/route-grid/search?origin=abidjan&destination=abidjan')->assertStatus(422);
        $this->getJson('/api/route-grid/search?origin=abidjan&destination=inconnue')->assertStatus(422);
    }

    #[Test]
    public function une_liaison_sans_ligne_retourne_une_liste_vide(): void
    {
        $this->getJson('/api/route-grid/search?origin=abidjan&destination=bouake')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    #[Test]
    public function l_administrateur_cree_une_ligne_avec_des_horaires_tries_et_sans_doublon(): void
    {
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->postJson('/api/route-grid', [
            'origin_city_id' => $this->abidjan->id,
            'destination_city_id' => $this->bouake->id,
            'company_name' => 'UTB',
            'price' => 7000,
            'duration_minutes' => 300,
            'departure_times' => ['18:00', '06:00', '18:00'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.departure_times', ['06:00', '18:00'])
            ->assertJsonPath('data.origin_city.name', 'Abidjan');
    }

    #[Test]
    public function une_liaison_ne_peut_pas_relier_une_ville_a_elle_meme(): void
    {
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->postJson('/api/route-grid', [
            'origin_city_id' => $this->abidjan->id,
            'destination_city_id' => $this->abidjan->id,
            'price' => 5000,
        ])->assertStatus(422)->assertJsonValidationErrors('destination_city_id');
    }

    #[Test]
    public function un_horaire_mal_forme_est_refuse(): void
    {
        $admin = $this->userWithRole('platform_admin');

        $this->actingAs($admin)->postJson('/api/route-grid', [
            'origin_city_id' => $this->abidjan->id,
            'destination_city_id' => $this->bouake->id,
            'price' => 5000,
            'departure_times' => ['6h du matin'],
        ])->assertStatus(422)->assertJsonValidationErrors('departure_times.0');
    }

    #[Test]
    public function l_administrateur_modifie_et_supprime_une_ligne(): void
    {
        $admin = $this->userWithRole('platform_admin');
        $entry = RouteGridEntry::factory()->create([
            'origin_city_id' => $this->abidjan->id, 'destination_city_id' => $this->bouake->id,
        ]);

        $this->actingAs($admin)->patchJson("/api/route-grid/{$entry->id}", ['price' => 9000, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.price', 9000)
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($admin)->deleteJson("/api/route-grid/{$entry->id}")->assertNoContent();
        $this->assertModelMissing($entry);
    }

    #[Test]
    public function un_gestionnaire_de_compagnie_ne_gere_pas_la_grille(): void
    {
        $manager = $this->userWithRole('company_manager', Company::factory()->create());

        $this->actingAs($manager)->getJson('/api/route-grid')->assertForbidden();
        $this->actingAs($manager)->postJson('/api/route-grid', [
            'origin_city_id' => $this->abidjan->id,
            'destination_city_id' => $this->bouake->id,
            'price' => 5000,
        ])->assertForbidden();
    }

    #[Test]
    public function la_liste_d_administration_montre_aussi_les_lignes_inactives(): void
    {
        $admin = $this->userWithRole('platform_admin');
        RouteGridEntry::factory()->count(2)->create();
        RouteGridEntry::factory()->inactive()->create();

        $this->actingAs($admin)->getJson('/api/route-grid')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }
}
