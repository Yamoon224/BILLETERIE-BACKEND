<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Booking\Http\Requests\OfflineSyncRequest;
use App\Domains\Booking\Services\OfflineSyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Synchronisation des ventes hors ligne.
 *
 * Repond **200 et non 207**, meme quand une partie du lot est refusee. Le lot
 * a bien ete traite dans son entier ; ce sont ses elements qui ont des sorts
 * differents, et chacun porte son verdict. Un 207 obligerait la tablette a
 * traiter la reponse HTTP comme un echec partiel alors que la seule chose a
 * faire est de lire les verdicts, un par un.
 *
 * Le resume en tete evite a l'application de recompter : sur une tablette qui
 * remonte cent ventes en fin de vacation, l'agent veut voir « 98 acceptees,
 * 2 refusees » avant d'ouvrir le detail.
 */
class OfflineSyncController extends Controller
{
    public function __construct(private readonly OfflineSyncService $sync) {}

    public function __invoke(OfflineSyncRequest $request): JsonResponse
    {
        $results = $this->sync->synchronise($request->sales(), $request->user()?->id);

        $counts = array_count_values(array_column($results, 'status'));

        return response()->json([
            'data' => [
                'summary' => [
                    'total' => count($results),
                    'accepted' => $counts['accepted'] ?? 0,
                    'duplicate' => $counts['duplicate'] ?? 0,
                    'rejected' => $counts['rejected'] ?? 0,
                ],
                'results' => $results,
            ],
        ]);
    }
}
