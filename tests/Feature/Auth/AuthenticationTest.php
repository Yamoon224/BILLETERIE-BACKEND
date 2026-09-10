<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_utilisateur_se_connecte_et_recoit_ses_permissions(): void
    {
        $agent = $this->userWithRole('agent');

        $response = $this->postJson('/api/login', [
            'email' => $agent->email,
            'password' => 'password',
            'device_name' => 'tablette-guichet-01',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'roles', 'permissions']]])
            ->assertJsonPath('data.user.roles', ['agent']);

        $this->assertContains('tickets.validate', $response->json('data.user.permissions'));
        $this->assertNotNull($agent->refresh()->last_login_at);
    }

    /**
     * Meme message que le compte existe ou non : distinguer les deux cas
     * transformerait le formulaire en oracle d'enumeration de comptes.
     */
    #[Test]
    public function des_identifiants_invalides_renvoient_un_message_neutre(): void
    {
        $user = User::factory()->create();

        $wrongPassword = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'faux']);
        $unknownEmail = $this->postJson('/api/login', ['email' => 'inconnu@example.test', 'password' => 'faux']);

        $wrongPassword->assertStatus(422)->assertJsonPath('error_code', 'validation_failed');
        $this->assertSame($wrongPassword->json('errors.email'), $unknownEmail->json('errors.email'));
    }

    /** Un agent desactive garde son historique mais perd l'acces. */
    #[Test]
    public function un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(422);
    }

    /**
     * Le role est impose par le serveur : une inscription publique qui
     * accepterait un role permettrait a n'importe qui de se declarer
     * administrateur.
     */
    #[Test]
    public function l_inscription_cree_un_voyageur_quel_que_soit_le_role_demande(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $response = $this->postJson('/api/register', [
            'name' => 'Awa Kone',
            'email' => 'awa@example.test',
            'phone' => '+2250700000050',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'roles' => ['platform_admin'],
        ]);

        $response->assertCreated()->assertJsonPath('data.user.roles', ['passenger']);
        $this->assertSame([], $response->json('data.user.permissions'));
    }

    #[Test]
    public function la_deconnexion_revoque_le_jeton(): void
    {
        $user = $this->userWithRole('agent');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    #[Test]
    public function une_route_protegee_repond_401_en_json_sans_jeton(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthenticated');
    }

    #[Test]
    public function le_changement_de_mot_de_passe_exige_l_ancien(): void
    {
        $user = $this->userWithRole('agent');

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'faux',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->actingAs($user)->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertOk();
    }
}
