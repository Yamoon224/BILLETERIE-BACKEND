<?php

namespace App\Domains\Users\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): User;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): User;

    /** @param  array<string, mixed>  $attributes */
    public function update(User $user, array $attributes): User;

    public function delete(User $user): void;

    /**
     * Nombre de ventes encaissees par ce compte.
     *
     * Un agent qui a vendu n'est jamais supprime : sa suppression laisserait
     * des ventes sans auteur, et c'est justement le nom de l'agent qu'on
     * cherche quand une caisse ne tombe pas juste.
     */
    public function countSales(User $user): int;
}
