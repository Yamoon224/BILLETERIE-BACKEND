<?php

namespace App\Domains\Housing\Http\Controllers;

use App\Domains\Housing\Http\Requests\StoreApartmentRequest;
use App\Domains\Housing\Http\Requests\UpdateApartmentRequest;
use App\Domains\Housing\Http\Resources\ApartmentResource;
use App\Domains\Housing\Services\ApartmentService;
use App\Domains\Shared\Enums\ListingStatus;
use App\Domains\Shared\Exceptions\PartnerScopeViolationException;
use App\Domains\Shared\Http\Requests\UpdateListingStatusRequest;
use App\Domains\Shared\Support\PartnerScope;
use App\Http\Controllers\Controller;
use App\Models\Apartment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Catalogue d'appartements meubles.
 *
 * `search` (public, zone voyageur) n'expose que les fiches actives ; `index`
 * (authentifie, permission `housing.view`) expose tous les statuts, borne par
 * partenaire via `PartnerScope`.
 */
class ApartmentController extends Controller
{
    public function __construct(private readonly ApartmentService $apartments) {}

    /** Recherche publique : parcours voyageur, avant tout compte. */
    public function search(Request $request): AnonymousResourceCollection
    {
        return ApartmentResource::collection($this->apartments->search(
            $request->only(
                'search', 'city_id', 'neighborhood', 'capacity',
                'min_price', 'max_price', 'is_featured', 'sort', 'direction',
            ),
            $request->integer('per_page', 15),
        ));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return ApartmentResource::collection($this->apartments->list(
            PartnerScope::apply(
                $request->only('search', 'city_id', 'is_featured', 'is_active', 'status', 'sort', 'direction'),
                $request->user(),
            ),
            $request->integer('per_page', 15),
        ));
    }

    public function store(StoreApartmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $scope = PartnerScope::forUser($request->user());

        if ($scope !== null) {
            $data['partner_id'] = $scope;
        }

        return (new ApartmentResource($this->apartments->create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Apartment $apartment): ApartmentResource
    {
        $this->authorizeApartment($request, $apartment);

        return new ApartmentResource($this->apartments->find($apartment->id));
    }

    public function update(UpdateApartmentRequest $request, Apartment $apartment): ApartmentResource
    {
        $this->authorizeApartment($request, $apartment);

        return new ApartmentResource($this->apartments->update($apartment, $request->validated()));
    }

    /**
     * Validation ou rejet d'une fiche par l'administrateur de plateforme
     * (voir routes : reserve a `platform.manage`).
     */
    public function changeStatus(UpdateListingStatusRequest $request, Apartment $apartment): ApartmentResource
    {
        return new ApartmentResource(
            $this->apartments->changeStatus($apartment, ListingStatus::from($request->validated('status'))),
        );
    }

    public function destroy(Request $request, Apartment $apartment): Response
    {
        $this->authorizeApartment($request, $apartment);
        $this->apartments->delete($apartment);

        return response()->noContent();
    }

    private function authorizeApartment(Request $request, Apartment $apartment): void
    {
        if (! PartnerScope::allows($request->user(), $apartment->partner_id)) {
            throw PartnerScopeViolationException::make();
        }
    }
}
