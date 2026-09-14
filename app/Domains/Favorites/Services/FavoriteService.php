<?php

namespace App\Domains\Favorites\Services;

use App\Domains\Favorites\Contracts\FavoriteRepositoryContract;
use App\Models\Favorite;

/** Trajets favoris du voyageur connecte. */
final class FavoriteService
{
    public function __construct(private readonly FavoriteRepositoryContract $favorites) {}

    /** @return list<Favorite> */
    public function forUser(string $userId): array
    {
        return $this->favorites->forUser($userId);
    }

    /**
     * Ajoute un trajet aux favoris, sans jamais le dupliquer.
     *
     * Idempotent a dessein : un double tap sur le coeur, frequent sur un
     * reseau qui hesite, doit retrouver le favori deja enregistre plutot que
     * d'echouer sur une contrainte d'unicite.
     */
    public function addForUser(string $userId, string $originCityId, string $destinationCityId): Favorite
    {
        return $this->favorites->firstOrCreate($userId, $originCityId, $destinationCityId);
    }

    public function removeForUser(string $favoriteId, string $userId): void
    {
        $this->favorites->delete($this->favorites->findOwnedOrFail($favoriteId, $userId));
    }
}
