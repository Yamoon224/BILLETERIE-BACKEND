<?php

namespace App\Domains\RouteGrid\Http\Controllers;

use App\Domains\RouteGrid\Http\Requests\SearchRouteGridRequest;
use App\Domains\RouteGrid\Http\Requests\StoreRouteGridEntryRequest;
use App\Domains\RouteGrid\Http\Requests\UpdateRouteGridEntryRequest;
use App\Domains\RouteGrid\Http\Resources\RouteGridEntryResource;
use App\Domains\RouteGrid\Services\RouteGridService;
use App\Http\Controllers\Controller;
use App\Models\RouteGridEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Grille des trajets.
 *
 * `search` est **publique** (le voyageur la consulte apres sa recherche, avant
 * tout compte) et n'expose que les lignes actives. Le reste est reserve a
 * l'administrateur de la plateforme (`platform.manage`).
 */
class RouteGridController extends Controller
{
    public function __construct(private readonly RouteGridService $grid) {}

    public function search(SearchRouteGridRequest $request): AnonymousResourceCollection
    {
        return RouteGridEntryResource::collection($this->grid->forRoute(
            (string) $request->validated('origin'),
            (string) $request->validated('destination'),
        ));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return RouteGridEntryResource::collection($this->grid->list(
            $request->only('search', 'is_active', 'sort', 'direction'),
            $request->integer('per_page', 15),
        ));
    }

    public function store(StoreRouteGridEntryRequest $request): JsonResponse
    {
        return (new RouteGridEntryResource($this->grid->create($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateRouteGridEntryRequest $request, RouteGridEntry $routeGridEntry): RouteGridEntryResource
    {
        return new RouteGridEntryResource($this->grid->update($routeGridEntry, $request->validated()));
    }

    public function destroy(RouteGridEntry $routeGridEntry): Response
    {
        $this->grid->delete($routeGridEntry);

        return response()->noContent();
    }
}
