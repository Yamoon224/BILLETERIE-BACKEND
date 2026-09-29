<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Reporting\Contracts\SalesReportReaderContract;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Cloture de caisse de l'agent connecte : ce que lui, et lui seul, a
 * physiquement encaisse un jour donne. Aucun parametre de compagnie ou de
 * gare ici - l'agent ne consulte jamais la caisse d'un collegue, l'identifiant
 * vient du jeton, jamais de la requete.
 */
class CashierSummaryController extends Controller
{
    public function __construct(private readonly SalesReportReaderContract $reports) {}

    public function __invoke(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? Carbon::parse((string) $request->string('date')) : Carbon::today();

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                ...$this->reports->cashierSummary($request->user()->id, $date),
            ],
        ]);
    }
}
