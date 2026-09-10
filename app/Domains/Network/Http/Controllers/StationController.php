<?php

namespace App\Domains\Network\Http\Controllers;

use App\Domains\Network\Http\Requests\StoreStationRequest;
use App\Domains\Network\Http\Requests\UpdateStationRequest;
use App\Domains\Network\Http\Resources\StationResource;
use App\Domains\Network\Services\StationService;
use App\Domains\Shared\Exceptions\CompanyScopeViolationException;
use App\Domains\Shared\Support\CompanyScope;
use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StationController extends Controller
{
    public function __construct(private readonly StationService $stations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return StationResource::collection($this->stations->list(
            CompanyScope::apply(
                $request->only('city_id', 'search', 'is_active', 'sort', 'direction'),
                $request->user(),
            ),
            $request->integer('per_page', 15),
        ));
    }

    public function store(StoreStationRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Un gestionnaire ne cree de gare que pour sa compagnie : la valeur
        // eventuellement envoyee est ecrasee, jamais refusee — le client n'a
        // aucune raison de connaitre son propre identifiant de compagnie.
        $scope = CompanyScope::forUser($request->user());

        if ($scope !== null) {
            $data['company_id'] = $scope;
        }

        return (new StationResource($this->stations->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Station $station): StationResource
    {
        $this->authorizeStation($request, $station, allowShared: true);

        return new StationResource($this->stations->find($station->id));
    }

    public function update(UpdateStationRequest $request, Station $station): StationResource
    {
        $this->authorizeStation($request, $station);

        return new StationResource($this->stations->update($station, $request->validated()));
    }

    public function destroy(Request $request, Station $station): Response
    {
        $this->authorizeStation($request, $station);
        $this->stations->delete($station);

        return response()->noContent();
    }

    /**
     * Une gare partagee (sans compagnie) est **consultable** par toutes les
     * compagnies qui en partent, mais seul l'administrateur plateforme peut la
     * modifier : elle sert de point de depart a des concurrents, et laisser
     * l'un d'eux la renommer ou la desactiver couperait les departs des autres.
     */
    private function authorizeStation(Request $request, Station $station, bool $allowShared = false): void
    {
        if ($allowShared && $station->company_id === null) {
            return;
        }

        if (! CompanyScope::allows($request->user(), $station->company_id)) {
            throw CompanyScopeViolationException::make();
        }
    }
}
