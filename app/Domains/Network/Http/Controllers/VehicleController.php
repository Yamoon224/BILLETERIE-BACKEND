<?php

namespace App\Domains\Network\Http\Controllers;

use App\Domains\Network\Http\Requests\StoreVehicleRequest;
use App\Domains\Network\Http\Requests\UpdateVehicleRequest;
use App\Domains\Network\Http\Resources\VehicleResource;
use App\Domains\Network\Services\VehicleService;
use App\Domains\Shared\Exceptions\CompanyScopeViolationException;
use App\Domains\Shared\Support\CompanyScope;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $vehicles) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return VehicleResource::collection($this->vehicles->list(
            CompanyScope::apply(
                $request->only('search', 'class', 'is_active', 'sort', 'direction'),
                $request->user(),
            ),
            $request->integer('per_page', 15),
        ));
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $scope = CompanyScope::forUser($request->user());

        if ($scope !== null) {
            $data['company_id'] = $scope;
        }

        return (new VehicleResource($this->vehicles->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorizeVehicle($request, $vehicle);

        return new VehicleResource($this->vehicles->find($vehicle->id));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorizeVehicle($request, $vehicle);

        return new VehicleResource($this->vehicles->update($vehicle, $request->validated()));
    }

    public function destroy(Request $request, Vehicle $vehicle): Response
    {
        $this->authorizeVehicle($request, $vehicle);
        $this->vehicles->delete($vehicle);

        return response()->noContent();
    }

    private function authorizeVehicle(Request $request, Vehicle $vehicle): void
    {
        if (! CompanyScope::allows($request->user(), $vehicle->company_id)) {
            throw CompanyScopeViolationException::make();
        }
    }
}
