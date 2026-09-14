<?php

namespace App\Domains\Favorites\Contracts;

use App\Models\Favorite;

interface FavoriteRepositoryContract
{
    /** @return list<Favorite> */
    public function forUser(string $userId): array;

    /**
     * Cree le favori s'il n'existe pas encore, ou renvoie celui deja
     * enregistre.
     */
    public function firstOrCreate(string $userId, string $originCityId, string $destinationCityId): Favorite;

    /** Leve une 404 si le favori n'existe pas ou appartient a un autre voyageur. */
    public function findOwnedOrFail(string $id, string $userId): Favorite;

    public function delete(Favorite $favorite): void;
}
