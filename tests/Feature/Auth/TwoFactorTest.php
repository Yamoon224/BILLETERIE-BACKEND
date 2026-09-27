<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    /** Reservee aux administrateurs : un agent ou un gestionnaire ne compromet qu'une seule compagnie ou un seul guichet. */
    #[Test]
    public function seul_un_administrateur_de_plateforme_peut_activer_la_2fa(): void
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)->postJson('/api/me/two-factor')->assertForbidden();
    }

    #[Test]
    public function un_administrateur_active_puis_confirme_la_2fa(): void
    {
        $admin = $this->userWithRole('platform_admin');

        $enable = $this->actingAs($admin)->postJson('/api/me/two-factor')->assertOk();
        $secret = $enable->json('data.secret');
        $this->assertNotEmpty($secret);
        $this->assertNotEmpty($enable->json('data.otpauth_url'));

        // Un secret genere n'exige encore rien a la connexion : seule une
        // confirmation reussie active reellement l'exigence.
        $this->assertNull($admin->refresh()->two_factor_confirmed_at);

        $this->actingAs($admin)->postJson('/api/me/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $validCode = (new Google2FA())->getCurrentOtp($secret);
        $this->actingAs($admin)->postJson('/api/me/two-factor/confirm', ['code' => $validCode])->assertOk();

        $this->assertNotNull($admin->refresh()->two_factor_confirmed_at);
    }

    #[Test]
    public function la_connexion_exige_le_code_une_fois_la_2fa_confirmee(): void
    {
        $admin = $this->userWithRole('platform_admin');
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $this->postJson('/api/login', ['login' => $admin->email, 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/login', [
            'login' => $admin->email,
            'password' => 'password',
            'code' => $google2fa->getCurrentOtp($secret),
        ])->assertOk();
    }

    /** Un compte sans 2FA confirmee n'en demande toujours pas : la fonctionnalite est retrocompatible. */
    #[Test]
    public function la_connexion_ne_demande_rien_sans_2fa_confirmee(): void
    {
        $admin = $this->userWithRole('platform_admin');

        $this->postJson('/api/login', ['login' => $admin->email, 'password' => 'password'])->assertOk();
    }

    #[Test]
    public function desactiver_la_2fa_exige_le_mot_de_passe_actuel(): void
    {
        $admin = $this->userWithRole('platform_admin');
        $admin->forceFill([
            'two_factor_secret' => (new Google2FA())->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($admin)->deleteJson('/api/me/two-factor', ['password' => 'faux'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->actingAs($admin)->deleteJson('/api/me/two-factor', ['password' => 'password'])->assertOk();

        $this->assertNull($admin->refresh()->two_factor_secret);
        $this->assertNull($admin->refresh()->two_factor_confirmed_at);
    }
}
