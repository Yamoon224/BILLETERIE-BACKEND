<?php

namespace App\Domains\Favorites\Repositories;

use App\Domains\Favorites\Contracts\FavoriteRepositoryContract;
use App\Models\Favorite;

final class EloquentFavoriteRepository implements FavoriteRepositoryContract
{
    private const WITH = ['originCity:id,name,slug', 'destinationCity:id,name,slug'];

    /** @return list<Favorite> */
    public function forUser(string $userId): array
    {
        return Favorite::query()
            ->with(self::WITH)
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->all();
    }

    public function firstOrCreate(string $userId, string $originCityId, string $destinationCityId): Favorite
    {
        $favorite = Favorite::query()->firstOrCreate([
            'user_id' => $userId,
            'origin_city_id' => $originCityId,
            'destination_city_id' => $destinationCityId,
        ]);

        return $favorite->load(self::WITH);
    }

    public function findOwnedOrFail(string $id, string $userId): Favorite
    {
        // Filtre sur le titulaire avant le `findOrFail` : le favori d'un autre
        // voyageur ne doit meme pas se distinguer d'un identifiant inexistant.
        return Favorite::query()->where('user_id', $userId)->findOrFail($id);
    }

    public function delete(Favorite $favorite): void
    {
        $favorite->delete();
    }
}
