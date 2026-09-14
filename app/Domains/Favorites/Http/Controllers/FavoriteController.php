<?php

namespace App\Domains\Favorites\Http\Controllers;

use App\Domains\Favorites\Http\Requests\StoreFavoriteRequest;
use App\Domains\Favorites\Http\Resources\FavoriteResource;
use App\Domains\Favorites\Services\FavoriteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Trajets favoris.
 *
 * Aucune permission ici, comme pour `/me/bookings` : ces routes n'agissent
 * que sur les favoris de l'appelant, jamais sur ceux d'un autre voyageur.
 */
class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $favorites) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return FavoriteResource::collection($this->favorites->forUser($request->user()->id));
    }

    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $favorite = $this->favorites->addForUser(
            $request->user()->id,
            $request->string('origin_city_id')->toString(),
            $request->string('destination_city_id')->toString(),
        );

        return (new FavoriteResource($favorite))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, string $favorite): Response
    {
        $this->favorites->removeForUser($favorite, $request->user()->id);

        return response()->noContent();
    }
}
