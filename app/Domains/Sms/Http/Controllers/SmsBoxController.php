<?php

namespace App\Domains\Sms\Http\Controllers;

use App\Domains\Sms\Http\Resources\SimCardResource;
use App\Domains\Sms\Http\Resources\SmsDispatchResource;
use App\Domains\Sms\Services\SmsBoxService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Vue d'ensemble du SMS Box : indicateurs du jour, etat des cartes SIM et
 * dix derniers envois — l'ecran d'ouverture, assemble en une seule reponse
 * pour la meme raison que le tableau de bord d'activite (voir
 * DashboardService).
 */
class SmsBoxController extends Controller
{
    public function __construct(private readonly SmsBoxService $smsBox) {}

    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'stats' => $this->smsBox->stats(),
            'sim_cards' => SimCardResource::collection($this->smsBox->simCards()),
            'recent_queue' => SmsDispatchResource::collection($this->smsBox->queue([], 10)->items()),
        ]]);
    }
}
