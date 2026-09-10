<?php

namespace App\Domains\Network\Http\Controllers;

use App\Domains\Network\Http\Requests\StoreCityRequest;
use App\Domains\Network\Http\Requests\UpdateCityRequest;
use App\Domains\Network\Http\Resources\CityResource;
use App\Domains\Network\Services\CityService;
use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Referentiel des villes.
 *
 * La liste des villes actives est **publique** : elle alimente le formulaire
 * de recherche du site voyageur, consulte avant toute connexion.
 */
class CityController extends Controller
{
    public function __construct(private readonly CityService $cities) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return CityResource::collection($this->cities->list(
            $request->only('search', 'is_active', 'sort', 'direction'),
            $request->integer('per_page', 15),
        ));
    }

    /** Villes actives, sans pagination : alimente les selecteurs de recherche. */
    public function options(): AnonymousResourceCollection
    {
        return CityResource::collection($this->cities->allActive());
    }

    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = $this->cities->create($request->validated());

        return (new CityResource($city))->response()->setStatusCode(201);
    }

    public function show(City $city): CityResource
    {
        return new CityResource($city);
    }

    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        return new CityResource($this->cities->update($city, $request->validated()));
    }

    public function destroy(City $city): Response
    {
        $this->cities->delete($city);

        return response()->noContent();
    }
}
