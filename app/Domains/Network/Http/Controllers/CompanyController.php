<?php

namespace App\Domains\Network\Http\Controllers;

use App\Domains\Network\Http\Requests\StoreCompanyRequest;
use App\Domains\Network\Http\Requests\UpdateCompanyRequest;
use App\Domains\Network\Http\Resources\CompanyResource;
use App\Domains\Network\Services\CompanyService;
use App\Domains\Shared\Exceptions\CompanyScopeViolationException;
use App\Domains\Shared\Support\CompanyScope;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Compagnies partenaires.
 *
 * L'administrateur plateforme les gere toutes ; un gestionnaire ne voit et ne
 * modifie que la sienne. La borne est resolue par `CompanyScope` a partir du
 * jeton, jamais lue dans la requete.
 */
class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companies) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only('search', 'is_active', 'sort', 'direction');

        // Un gestionnaire ne « liste » qu'une compagnie : la sienne. Le filtre
        // par identifiant est impose ici plutot que refuse, pour que l'ecran
        // de liste reste utilisable avec le meme code.
        $scope = CompanyScope::forUser($request->user());

        if ($scope !== null) {
            $filters['id'] = $scope;
        }

        return CompanyResource::collection(
            $this->companies->list($filters, $request->integer('per_page', 15)),
        );
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = $this->companies->create($request->validated());

        return (new CompanyResource($company))->response()->setStatusCode(201);
    }

    public function show(Request $request, Company $company): CompanyResource
    {
        $this->authorizeCompany($request, $company);

        return new CompanyResource($this->companies->find($company->id));
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        $this->authorizeCompany($request, $company);

        return new CompanyResource($this->companies->update($company, $request->validated()));
    }

    public function destroy(Company $company): Response
    {
        // Suppression reservee a l'administrateur plateforme (voir routes) :
        // une compagnie ne se supprime pas elle-meme.
        $this->companies->delete($company);

        return response()->noContent();
    }

    private function authorizeCompany(Request $request, Company $company): void
    {
        if (! CompanyScope::allows($request->user(), $company->id)) {
            throw CompanyScopeViolationException::make();
        }
    }
}
