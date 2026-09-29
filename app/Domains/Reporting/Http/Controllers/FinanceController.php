<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Reporting\DTOs\ReportFilters;
use App\Domains\Reporting\Services\FinanceService;
use App\Domains\Shared\Support\CompanyScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Finances de la plateforme : commission par compagnie et par moyen de
 * paiement. Reserve a l'administrateur (permission `finance.view`) : c'est
 * une vue transverse aux compagnies, jamais accessible a l'une d'entre
 * elles.
 */
class FinanceController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function __invoke(Request $request): JsonResponse
    {
        $filters = ReportFilters::fromRequest(
            $request->only('from', 'to', 'station_id'),
            CompanyScope::forUser($request->user()),
        );

        return response()->json(['data' => $this->finance->overview($filters)]);
    }
}
