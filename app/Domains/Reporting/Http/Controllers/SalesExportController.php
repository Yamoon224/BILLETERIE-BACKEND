<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Reporting\DTOs\ReportFilters;
use App\Domains\Reporting\Services\SalesExportService;
use App\Domains\Shared\Support\CompanyScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export des donnees d'activite, exigence explicite du cahier des charges.
 *
 * Diffuse en flux : un export de saison porte sur des dizaines de milliers de
 * lignes, et les assembler en memoire avant envoi ferait tomber le processus
 * exactement au moment ou l'export devient utile.
 */
class SalesExportController extends Controller
{
    public function __construct(private readonly SalesExportService $exports) {}

    public function bookings(Request $request): StreamedResponse
    {
        return $this->exports->bookingsCsv($this->filters($request));
    }

    public function occupancy(Request $request): StreamedResponse
    {
        return $this->exports->occupancyCsv($this->filters($request));
    }

    private function filters(Request $request): ReportFilters
    {
        return ReportFilters::fromRequest(
            $request->only('from', 'to', 'station_id'),
            CompanyScope::forUser($request->user()),
        );
    }
}
