<?php

namespace App\Domains\CarRental\Http\Controllers;

use App\Domains\CarRental\Http\Requests\StoreRentalVehicleRequest;
use App\Domains\CarRental\Http\Requests\UpdateRentalVehicleRequest;
use App\Domains\CarRental\Http\Resources\RentalVehicleResource;
use App\Domains\CarRental\Services\RentalVehicleService;
use App\Domains\Shared\Exceptions\PartnerScopeViolationException;
use App\Domains\Shared\Support\PartnerScope;
use App\Http\Controllers\Controller;
use App\Models\RentalVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Catalogue de vehicules de location courte duree.
 *
 * `search` (public, zone voyageur) n'expose que les fiches actives ; `index`
 * (authentifie, permission `car_rental.view`) expose tous les statuts, borne
 * par partenaire via `PartnerScope`.
 */
class RentalVehicleController extends Controller
{
    public function __construct(private readonly RentalVehicleService $vehicles) {}

    /** Recherche publique : parcours voyageur, avant tout compte. */
    public function search(Request $request): AnonymousResourceCollection
    {
        return RentalVehicleResource::collection($this->vehicles->search(
            $request->only(
                'search', 'city_id', 'category', 'transmission', 'seats',
                'min_price', 'max_price', 'with_driver_available', 'is_featured',
                'sort', 'direction',
            ),
            $request->integer('per_page', 15),
        ));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return RentalVehicleResource::collection($this->vehicles->list(
            PartnerScope::apply(
                $request->only('search', 'city_id', 'category', 'is_featured', 'is_active', 'sort', 'direction'),
                $request->user(),
            ),
            $request->integer('per_page', 15),
        ));
    }

    public function store(StoreRentalVehicleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $scope = PartnerScope::forUser($request->user());

        if ($scope !== null) {
            $data['partner_id'] = $scope;
        }

        return (new RentalVehicleResource($this->vehicles->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, RentalVehicle $rentalVehicle): RentalVehicleResource
    {
        $this->authorizeRentalVehicle($request, $rentalVehicle);

        return new RentalVehicleResource($this->vehicles->find($rentalVehicle->id));
    }

    public function update(UpdateRentalVehicleRequest $request, RentalVehicle $rentalVehicle): RentalVehicleResource
    {
        $this->authorizeRentalVehicle($request, $rentalVehicle);

        return new RentalVehicleResource($this->vehicles->update($rentalVehicle, $request->validated()));
    }

    public function destroy(Request $request, RentalVehicle $rentalVehicle): Response
    {
        $this->authorizeRentalVehicle($request, $rentalVehicle);
        $this->vehicles->delete($rentalVehicle);

        return response()->noContent();
    }

    private function authorizeRentalVehicle(Request $request, RentalVehicle $rentalVehicle): void
    {
        if (! PartnerScope::allows($request->user(), $rentalVehicle->partner_id)) {
            throw PartnerScopeViolationException::make();
        }
    }
}
