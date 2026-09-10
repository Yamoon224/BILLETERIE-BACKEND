<?php

namespace App\Domains\Scheduling\Http\Controllers;

use App\Domains\Scheduling\Enums\TripStatus;
use App\Domains\Scheduling\Http\Requests\CancelTripRequest;
use App\Domains\Scheduling\Http\Requests\StoreTripRequest;
use App\Domains\Scheduling\Http\Requests\UpdateTripRequest;
use App\Domains\Scheduling\Http\Resources\TripResource;
use App\Domains\Scheduling\Services\TripSearchService;
use App\Domains\Scheduling\Services\TripService;
use App\Domains\Shared\Exceptions\CompanyScopeViolationException;
use App\Domains\Shared\Support\CompanyScope;
use App\Http\Controllers\Controller;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Programmation des departs, cote exploitant.
 *
 * La recherche voyageur, publique, vit dans `TripSearchController` : ce ne sont
 * pas les memes lecteurs, pas les memes droits, et pas les memes donnees
 * exposees.
 */
class TripController extends Controller
{
    public function __construct(
        private readonly TripService $trips,
        private readonly TripSearchService $search,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return TripResource::collection($this->trips->list(
            CompanyScope::apply(
                $request->only('itinerary_id', 'vehicle_id', 'status', 'from', 'to', 'search', 'sort', 'direction'),
                $request->user(),
            ),
            $request->integer('per_page', 15),
        ));
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $itinerary = Itinerary::findOrFail($request->input('itinerary_id'));
        $vehicle = Vehicle::findOrFail($request->input('vehicle_id'));

        $this->authorizeCompany($request, $itinerary->company_id);

        // Le vehicule doit appartenir a la compagnie de l'itineraire : sans
        // cette verification, un gestionnaire pourrait programmer un depart
        // sur le car d'un concurrent, qui le verrait apparaitre dans son parc.
        if ($vehicle->company_id !== $itinerary->company_id) {
            throw CompanyScopeViolationException::make();
        }

        $trip = $this->trips->create($request->validated(), $itinerary, $vehicle);

        return (new TripResource($this->trips->find($trip->id)))->response()->setStatusCode(201);
    }

    public function show(Request $request, Trip $trip): TripResource
    {
        $this->authorizeCompany($request, $trip->company_id);

        $availability = $this->search->availabilityFor($trip->id);
        $model = $availability->trip;
        $model->setAttribute('seats_taken', $availability->seatsTaken);

        return new TripResource($model);
    }

    public function update(UpdateTripRequest $request, Trip $trip): TripResource
    {
        $this->authorizeCompany($request, $trip->company_id);

        return new TripResource($this->trips->update($trip, $request->validated()));
    }

    /** Annulation d'un depart, avec son motif. */
    public function cancel(CancelTripRequest $request, Trip $trip): TripResource
    {
        $this->authorizeCompany($request, $trip->company_id);

        return new TripResource($this->trips->cancel($trip, (string) $request->input('reason')));
    }

    /** Ouverture de l'embarquement, depart effectif, arrivee. */
    public function changeStatus(Request $request, Trip $trip): TripResource
    {
        $this->authorizeCompany($request, $trip->company_id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', TripStatus::values())],
        ]);

        return new TripResource(
            $this->trips->changeStatus($trip, TripStatus::from($validated['status'])),
        );
    }

    /**
     * Plan de salle du depart : chaque place, et si elle est prise.
     *
     * Accessible sans authentification : c'est l'ecran de choix de place du
     * tunnel de reservation, ouvert avant toute creation de compte.
     */
    public function seatMap(Trip $trip): JsonResponse
    {
        // Le depart accompagne son plan : l'ecran de choix de place affiche
        // l'heure, le trajet et le prix, et la consultation detaillee d'un
        // depart est reservee a l'exploitation. Une seule reponse evite aussi
        // un aller-retour de plus sur un reseau mobile.
        $availability = $this->search->availabilityFor($trip->id);
        $model = $availability->trip;
        $model->setAttribute('seats_taken', $availability->seatsTaken);

        return response()->json([
            'data' => [
                ...$this->search->seatMapFor($trip->id),
                'trip' => new TripResource($model),
            ],
        ]);
    }

    public function destroy(Request $request, Trip $trip): Response
    {
        $this->authorizeCompany($request, $trip->company_id);
        $this->trips->delete($trip);

        return response()->noContent();
    }

    private function authorizeCompany(Request $request, ?string $companyId): void
    {
        if (! CompanyScope::allows($request->user(), $companyId)) {
            throw CompanyScopeViolationException::make();
        }
    }
}
