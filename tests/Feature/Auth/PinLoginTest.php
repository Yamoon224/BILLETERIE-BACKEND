<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Connexion rapide au guichet par code PIN.
 *
 * Reservee aux comptes agents : un mot de passe reste necessaire pour tout
 * autre role, et un PIN egare n'ouvre donc jamais plus qu'une caisse.
 */
class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_agent_se_connecte_avec_son_pin(): void
    {
        $agent = $this->userWithRole('agent');
        $agent->forceFill(['pin_code_hash' => '1234'])->save();

        $this->postJson('/api/login/pin', ['login' => $agent->email, 'pin' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $agent->id)
            ->assertJsonPath('data.user.roles', ['agent']);
    }

    #[Test]
    public function un_gestionnaire_ne_peut_pas_se_connecter_par_pin_meme_avec_un_pin_defini(): void
    {
        $manager = $this->userWithRole('company_manager');
        $manager->forceFill(['pin_code_hash' => '1234'])->save();

        $this->postJson('/api/login/pin', ['login' => $manager->email, 'pin' => '1234'])
            ->assertStatus(422);
    }

    #[Test]
    public function un_mauvais_pin_est_refuse(): void
    {
        $agent = $this->userWithRole('agent');
        $agent->forceFill(['pin_code_hash' => '1234'])->save();

        $this->postJson('/api/login/pin', ['login' => $agent->email, 'pin' => '0000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');
    }

    /** Cinq echecs bloquent le compte, meme avec le bon PIN a la sixieme tentative. */
    #[Test]
    public function le_compte_se_bloque_apres_cinq_echecs(): void
    {
        $agent = $this->userWithRole('agent');
        $agent->forceFill(['pin_code_hash' => '1234'])->save();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login/pin', ['login' => $agent->email, 'pin' => '0000'])->assertStatus(422);
        }

        $this->postJson('/api/login/pin', ['login' => $agent->email, 'pin' => '1234'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');
    }

    #[Test]
    public function un_agent_sans_pin_defini_ne_peut_pas_l_utiliser(): void
    {
        $agent = $this->userWithRole('agent');

        $this->postJson('/api/login/pin', ['login' => $agent->email, 'pin' => '1234'])
            ->assertStatus(422);
    }

    #[Test]
    public function le_pin_doit_faire_quatre_chiffres(): void
    {
        $agent = $this->userWithRole('agent');
        $agent->forceFill(['pin_code_hash' => '1234'])->save();

        $this->postJson('/api/login/pin', ['login' => $agent->email, 'pin' => '12345'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');
    }
}
