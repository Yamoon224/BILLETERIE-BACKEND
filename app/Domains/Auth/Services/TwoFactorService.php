<?php

namespace App\Domains\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Verification en deux etapes (TOTP, RFC 6238).
 *
 * Verifiee hors ligne, sans SMS ni e-mail a envoyer : le code se lit dans une
 * application d'authentification deja installee sur le telephone du titulaire
 * du compte, ce qui continue de fonctionner meme sans reseau au moment de se
 * connecter.
 */
final class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Demarre ou redemarre une inscription : le secret genere reste sans effet
     * sur la connexion tant qu'il n'a pas ete confirme par un premier code
     * valide - un appel a cette methode n'exige donc rien de plus a la
     * prochaine connexion tant que `confirm()` n'a pas ete appelee.
     *
     * @return array{secret: string, otpauth_url: string}
     */
    public function startEnrollment(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauth_url' => $this->google2fa->getQRCodeUrl('Kaara', $user->email, $secret),
        ];
    }

    public function confirmEnrollment(User $user, string $code): void
    {
        if ($user->two_factor_secret === null) {
            throw ValidationException::withMessages([
                'code' => ['Aucune inscription 2FA en cours. Recommencez depuis le debut.'],
            ]);
        }

        if (! $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            throw ValidationException::withMessages([
                'code' => ['Code de verification invalide.'],
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    }

    /** @throws ValidationException si le mot de passe ne correspond pas. */
    public function disable(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Mot de passe incorrect.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }

    public function verifyLoginCode(User $user, ?string $code): bool
    {
        if ($code === null || trim($code) === '' || $user->two_factor_secret === null) {
            return false;
        }

        return $this->google2fa->verifyKey($user->two_factor_secret, $code);
    }
}
