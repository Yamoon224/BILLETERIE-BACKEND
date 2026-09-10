<?php

namespace App\Domains\Users\Services;

use App\Domains\Users\Contracts\UserRepositoryContract;
use App\Domains\Users\Exceptions\UserNotDeletableException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Administration des comptes.
 *
 * Deux regles de separation des taches sont portees ici plutot que par un
 * middleware, parce qu'elles dependent de l'etat et pas seulement du role.
 *
 * **Un compte qui a encaisse ne se supprime pas.** Il se desactive. Supprimer
 * un agent laisserait des ventes sans auteur — precisement l'information qu'on
 * cherche quand une caisse de gare ne tombe pas juste le soir.
 *
 * **Personne ne supprime son propre compte.** Un administrateur qui s'efface
 * par megarde laisse une plateforme sans administrateur, situation dont on ne
 * sort que par la base de donnees.
 */
final class UserService
{
    public function __construct(private readonly UserRepositoryContract $users) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $perPage);
    }

    public function find(string $id): User
    {
        return $this->users->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roles
     */
    public function create(array $data, array $roles): User
    {
        return DB::transaction(function () use ($data, $roles): User {
            $user = $this->users->create($data);
            $user->syncRoles($roles);

            return $user->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $roles  null laisse les roles inchanges
     */
    public function update(User $user, array $data, ?array $roles = null): User
    {
        return DB::transaction(function () use ($user, $data, $roles): User {
            // Un mot de passe vide dans un formulaire d'edition signifie « ne
            // pas changer », jamais « effacer » : sans ce retrait, chaque
            // modification de nom reinitialiserait le mot de passe.
            if (($data['password'] ?? null) === null) {
                unset($data['password']);
            }

            $updated = $this->users->update($user, $data);

            if ($roles !== null) {
                $updated->syncRoles($roles);
            }

            return $updated->refresh();
        });
    }

    /** @throws UserNotDeletableException */
    public function delete(User $user, ?string $currentUserId = null): void
    {
        if ($currentUserId !== null && $user->id === $currentUserId) {
            throw UserNotDeletableException::self();
        }

        $sales = $this->users->countSales($user);

        if ($sales > 0) {
            throw UserNotDeletableException::hasSales($user->name, $sales);
        }

        $this->users->delete($user);
    }
}
