<?php

namespace Tests\Feature\Favorites;

use App\Models\City;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FavoriteFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_voyageur_ajoute_un_favori_et_le_retrouve_dans_sa_liste(): void
    {
        $voyageur = User::factory()->create();
        $abidjan = City::factory()->create(['name' => 'Abidjan', 'slug' => 'abidjan']);
        $bouake = City::factory()->create(['name' => 'Bouake', 'slug' => 'bouake']);

        $this->actingAs($voyageur)->postJson('/api/favorites', [
            'origin_city_id' => $abidjan->id,
            'destination_city_id' => $bouake->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.origin_city.slug', 'abidjan')
            ->assertJsonPath('data.destination_city.slug', 'bouake');

        $this->actingAs($voyageur)->getJson('/api/me/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * Un double tap sur le coeur, frequent sur un reseau qui hesite, ne doit
     * jamais produire deux lignes pour le meme trajet.
     */
    #[Test]
    public function ajouter_deux_fois_le_meme_trajet_ne_cree_pas_de_doublon(): void
    {
        $voyageur = User::factory()->create();
        $abidjan = City::factory()->create();
        $bouake = City::factory()->create();

        $payload = ['origin_city_id' => $abidjan->id, 'destination_city_id' => $bouake->id];

        $first = $this->actingAs($voyageur)->postJson('/api/favorites', $payload)->assertCreated();
        $second = $this->actingAs($voyageur)->postJson('/api/favorites', $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Favorite::query()->count());
    }

    #[Test]
    public function un_voyageur_retire_un_favori(): void
    {
        $voyageur = User::factory()->create();
        $favorite = Favorite::factory()->create(['user_id' => $voyageur->id]);

        $this->actingAs($voyageur)->deleteJson("/api/favorites/{$favorite->id}")
            ->assertNoContent();

        $this->assertSame(0, Favorite::query()->count());
    }

    /** Le favori d'un autre voyageur n'est ni visible ni supprimable. */
    #[Test]
    public function un_voyageur_ne_peut_pas_retirer_le_favori_d_un_autre(): void
    {
        $proprietaire = User::factory()->create();
        $curieux = User::factory()->create();
        $favorite = Favorite::factory()->create(['user_id' => $proprietaire->id]);

        $this->actingAs($curieux)->deleteJson("/api/favorites/{$favorite->id}")
            ->assertNotFound();

        $this->assertSame(1, Favorite::query()->count());
    }

    #[Test]
    public function le_depart_et_l_arrivee_doivent_differer(): void
    {
        $voyageur = User::factory()->create();
        $abidjan = City::factory()->create();

        $this->actingAs($voyageur)->postJson('/api/favorites', [
            'origin_city_id' => $abidjan->id,
            'destination_city_id' => $abidjan->id,
        ])->assertStatus(422);
    }
}
