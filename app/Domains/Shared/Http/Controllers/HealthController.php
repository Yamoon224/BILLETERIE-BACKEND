<?php

namespace App\Domains\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sonde de sante consommee par l'orchestrateur et les tests de deploiement.
 *
 * La connectivite base est verifiee explicitement : une API qui repond 200
 * alors que sa base est injoignable est le pire des signaux pour un
 * deploiement automatise — l'orchestrateur bascule le trafic sur une instance
 * incapable de vendre un seul billet.
 *
 * La presence de la cle de signature des billets est verifiee au meme titre :
 * sans elle, l'API repond, accepte des reservations, et echoue au moment
 * d'emettre le premier billet. Mieux vaut le savoir au demarrage.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseState(),
            'ticket_signing_key' => config('ticketing.signing.key') !== '' ? 'ok' : 'missing',
        ];

        $healthy = ! in_array('unreachable', $checks, true) && ! in_array('missing', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'payment_gateway' => config('payments.gateway'),
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function databaseState(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (Throwable) {
            return 'unreachable';
        }
    }
}
