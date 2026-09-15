<?php

namespace App\Domains\Partners\Http\Controllers;

use App\Domains\Partners\Http\Requests\StorePartnerRequest;
use App\Domains\Partners\Http\Requests\UpdatePartnerRequest;
use App\Domains\Partners\Http\Resources\PartnerResource;
use App\Domains\Partners\Services\PartnerService;
use App\Domains\Shared\Exceptions\PartnerScopeViolationException;
use App\Domains\Shared\Support\PartnerScope;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Proprietaires et agences partenaires.
 *
 * L'administrateur plateforme les gere tous ; un gestionnaire de partenaire ne
 * voit et ne modifie que le sien. La borne est resolue par `PartnerScope` a
 * partir du jeton, jamais lue dans la requete.
 */
class PartnerController extends Controller
{
    public function __construct(private readonly PartnerService $partners) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only('search', 'type', 'is_active', 'sort', 'direction');

        // Un gestionnaire ne « liste » qu'un partenaire : le sien.
        $scope = PartnerScope::forUser($request->user());

        if ($scope !== null) {
            $filters['id'] = $scope;
        }

        return PartnerResource::collection(
            $this->partners->list($filters, $request->integer('per_page', 15)),
        );
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $partner = $this->partners->create($request->validated());

        return (new PartnerResource($partner))->response()->setStatusCode(201);
    }

    public function show(Request $request, Partner $partner): PartnerResource
    {
        $this->authorizePartner($request, $partner);

        return new PartnerResource($this->partners->find($partner->id));
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): PartnerResource
    {
        $this->authorizePartner($request, $partner);

        return new PartnerResource($this->partners->update($partner, $request->validated()));
    }

    public function destroy(Partner $partner): Response
    {
        // Suppression reservee a l'administrateur plateforme (voir routes) :
        // un partenaire ne se supprime pas lui-meme.
        $this->partners->delete($partner);

        return response()->noContent();
    }

    private function authorizePartner(Request $request, Partner $partner): void
    {
        if (! PartnerScope::allows($request->user(), $partner->id)) {
            throw PartnerScopeViolationException::make();
        }
    }
}
