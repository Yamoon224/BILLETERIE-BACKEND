<?php

namespace App\Domains\Auth\Services;

use App\Models\Company;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
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
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    /**
     * @param  array{login: string, password: string, code?: string|null}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function attempt(array $credentials, string $deviceName = 'api'): array
    {
        $identifier = trim($credentials['login']);
        $password = $credentials['password'];
        $user = $this->resolveUser($identifier);

        if ($user === null || ! Hash::check($password, $user->password)) {
            // Message volontairement identique que le compte existe ou non :
            // distinguer les deux cas transformerait le formulaire en oracle
            // d'enumeration de comptes.
            throw ValidationException::withMessages([
                'login' => ['Identifiants invalides.'],
            ]);
        }

        // Un compte desactive ne peut plus se connecter, mais garde son
        // historique de ventes : on ne supprime pas un agent qui a encaisse.
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Ce compte est desactive. Contactez votre administrateur.'],
            ]);
        }

        // Seuls les comptes ayant confirme une inscription 2FA en exigent un
        // code : l'exiger d'office aurait verrouille tout compte admin cree
        // avant l'existence de cette fonctionnalite.
        if ($this->twoFactor->isEnabled($user) && ! $this->twoFactor->verifyLoginCode($user, $credentials['code'] ?? null)) {
            throw ValidationException::withMessages([
                'code' => ['Code de verification invalide.'],
            ]);
        }

        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        return [
            'user' => $user,
            'token' => $user->createToken($deviceName)->plainTextToken,
        ];
    }

    /**
     * Retrouve le compte vise par l'identifiant saisi : une adresse e-mail,
     * un numero de telephone, ou - pour le gestionnaire d'une compagnie de
     * transport - le code de sa compagnie (« STC », « UTB », ...), plus facile
     * a partager en interne qu'une adresse e-mail individuelle.
     */
    private function resolveUser(string $identifier): ?User
    {
        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false) {
            return User::where('email', mb_strtolower($identifier))->first();
        }

        $byCompanyCode = $this->resolveCompanyManager($identifier);
        if ($byCompanyCode !== null) {
            return $byCompanyCode;
        }

        $phone = PhoneNumber::normalize($identifier);

        return $phone !== null ? User::where('phone', $phone)->first() : null;
    }

    /**
     * Le code d'une compagnie identifie l'entreprise, pas un individu : il
     * ouvre donc la session de son gestionnaire, le seul compte qui en
     * represente l'identite aupres de la plateforme - un agent de vente garde
     * son adresse e-mail ou son telephone personnel pour se connecter.
     */
    private function resolveCompanyManager(string $code): ?User
    {
        $company = Company::whereRaw('UPPER(code) = ?', [mb_strtoupper($code)])->first();
        if ($company === null) {
            return null;
        }

        return User::where('company_id', $company->id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'company_manager'))
            ->oldest()
            ->first();
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
