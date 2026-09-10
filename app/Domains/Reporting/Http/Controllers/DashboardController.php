<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Reporting\DTOs\ReportFilters;
use App\Domains\Reporting\Services\DashboardService;
use App\Domains\Shared\Support\CompanyScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de bord de suivi d'activite.
 *
 * Le perimetre de compagnie vient du jeton et **jamais de la requete** : c'est
 * la frontiere qui empeche un gestionnaire de consulter la recette d'un
 * concurrent. Un administrateur plateforme, lui, voit l'ensemble.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        $filters = ReportFilters::fromRequest(
            $request->only('from', 'to', 'station_id'),
            CompanyScope::forUser($request->user()),
        );

        return response()->json(['data' => $this->dashboard->overview($filters)]);
    }
}
