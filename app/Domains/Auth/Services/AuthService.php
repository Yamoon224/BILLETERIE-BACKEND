<?php

namespace App\Domains\Auth\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Authentification par jeton Sanctum.
 *
 * Le frontend Next.js, l'application agent et l'API sont sur des origines
 * distinctes sans session partagee : le mode cookie/SPA ne s'applique pas ici,
 * et le client envoie `Authorization: Bearer {token}`.
 *
 * Le jeton du guichet a volontairement une duree de vie longue
 * (`SANCTUM_TOKEN_EXPIRATION`, 12 h par defaut) : un agent en gare travaille
 * une vacation entiere, parfois sans reseau, et une reconnexion en milieu de
 * journee bloquerait la file d'attente devant lui.
 */
final class AuthService
{
    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function attempt(array $credentials, string $deviceName = 'api'): array
    {
        if (! Auth::validate($credentials)) {
            // Message volontairement identique que le compte existe ou non :
            // distinguer les deux cas transformerait le formulaire en oracle
            // d'enumeration de comptes.
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        /** @var User $user */
        $user = User::where('email', $credentials['email'])->firstOrFail();

        // Un compte desactive ne peut plus se connecter, mais garde son
        // historique de ventes : on ne supprime pas un agent qui a encaisse.
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Ce compte est desactive. Contactez votre administrateur.'],
            ]);
        }

        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        return [
            'user' => $user,
            'token' => $user->createToken($deviceName)->plainTextToken,
        ];
    }

    /**
     * Inscription d'un voyageur.
     *
     * Le role est impose ici et jamais lu depuis la requete : une inscription
     * publique qui accepterait un role permettrait a n'importe qui de se
     * declarer administrateur.
     *
     * @param  array{name: string, email: string, phone?: string|null, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data, string $deviceName = 'web'): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        $user->assignRole('passenger');

        return [
            'user' => $user->refresh(),
            'token' => $user->createToken($deviceName)->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        // La route de deconnexion est protegee par auth:sanctum en mode jeton :
        // un jeton personnel existe donc toujours a ce stade.
        $user->currentAccessToken()->delete();
    }
}
