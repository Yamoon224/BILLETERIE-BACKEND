<?php

namespace App\Domains\Network\Http\Controllers;

use App\Domains\Network\Http\Requests\StoreItineraryRequest;
use App\Domains\Network\Http\Requests\UpdateItineraryRequest;
use App\Domains\Network\Http\Resources\ItineraryResource;
use App\Domains\Network\Services\ItineraryService;
use App\Domains\Shared\Exceptions\CompanyScopeViolationException;
use App\Domains\Shared\Support\CompanyScope;
use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ItineraryController extends Controller
{
    public function __construct(private readonly ItineraryService $itineraries) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ItineraryResource::collection($this->itineraries->list(
            CompanyScope::apply(
                $request->only('origin_city_id', 'destination_city_id', 'search', 'is_active', 'sort', 'direction'),
                $request->user(),
            ),
            $request->integer('per_page', 15),
        ));
    }

    public function store(StoreItineraryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $scope = CompanyScope::forUser($request->user());

        if ($scope !== null) {
            $data['company_id'] = $scope;
        }

        return (new ItineraryResource($this->itineraries->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Itinerary $itinerary): ItineraryResource
    {
        $this->authorizeItinerary($request, $itinerary);

        return new ItineraryResource($this->itineraries->find($itinerary->id));
    }

    public function update(UpdateItineraryRequest $request, Itinerary $itinerary): ItineraryResource
    {
        $this->authorizeItinerary($request, $itinerary);

        return new ItineraryResource($this->itineraries->update($itinerary, $request->validated()));
    }

    public function destroy(Request $request, Itinerary $itinerary): Response
    {
        $this->authorizeItinerary($request, $itinerary);
        $this->itineraries->delete($itinerary->load(['originCity', 'destinationCity']));

        return response()->noContent();
    }

    private function authorizeItinerary(Request $request, Itinerary $itinerary): void
    {
        if (! CompanyScope::allows($request->user(), $itinerary->company_id)) {
            throw CompanyScopeViolationException::make();
        }
    }
}
