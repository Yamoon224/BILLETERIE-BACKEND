<?php

namespace App\Domains\Users\Services;

use App\Domains\Users\Contracts\UserRepositoryContract;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Compte de l'utilisateur connecte.
 *
 * Separe de [[UserService]] parce que les regles ne sont pas les memes : ici,
 * personne ne change son propre role ni sa propre compagnie, et le changement
 * de mot de passe exige de connaitre l'ancien. Melanger les deux services
 * ferait cohabiter « l'administrateur agit sur autrui » et « l'utilisateur agit
 * sur lui-meme » dans les memes methodes, avec des `if` sur l'appelant.
 */
final class ProfileService
{
    public function __construct(private readonly UserRepositoryContract $users) {}

    /** @param  array<string, mixed>  $data */
    public function update(User $user, array $data): User
    {
        return $this->users->update($user, $data);
    }

    /**
     * Change le mot de passe apres verification de l'ancien.
     *
     * La verification n'est pas une formalite : un poste de guichet reste
     * souvent ouvert entre deux clients, et sans elle, quiconque passe devant
     * un ecran deverrouille pourrait s'approprier le compte de l'agent — et
     * encaisser sous son nom.
     */
    public function updatePassword(User $user, string $currentPassword, string $newPassword): User
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        return $this->users->update($user, ['password' => $newPassword]);
    }
}
