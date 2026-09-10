<?php

namespace App\Domains\Scheduling\Http\Controllers;

use App\Domains\Scheduling\DTOs\TripAvailability;
use App\Domains\Scheduling\Http\Requests\SearchTripsRequest;
use App\Domains\Scheduling\Http\Resources\TripResource;
use App\Domains\Scheduling\Services\TripSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Recherche de departs. **Endpoint public**, sans authentification.
 *
 * C'est le premier ecran du parcours voyageur : exiger un compte pour
 * consulter des horaires ferait perdre la moitie des visiteurs avant qu'ils
 * aient vu un prix. Le compte n'est demande qu'au moment de reserver.
 *
 * La reponse n'est pas paginee : une recherche porte sur un couple de villes
 * et une journee, ce qui donne quelques dizaines de departs au plus. Une
 * pagination sur ce volume couterait un aller-retour de plus sur un reseau
 * mobile pour rien.
 */
class TripSearchController extends Controller
{
    public function __construct(private readonly TripSearchService $search) {}

    public function __invoke(SearchTripsRequest $request): AnonymousResourceCollection
    {
        $results = $this->search->search($request->criteria());

        // Le remplissage, calcule en une requete pour toute la page, est pose
        // sur le modele : la ressource ne declenche ainsi aucune requete.
        $trips = array_map(static function (TripAvailability $availability) {
            $trip = $availability->trip;
            $trip->setAttribute('seats_taken', $availability->seatsTaken);

            return $trip;
        }, $results);

        return TripResource::collection($trips);
    }
}
