<?php

namespace App\Domains\Ticketing\Http\Resources;

use App\Domains\Ticketing\DTOs\ScanResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Verdict d'un scan, rendu a l'application agent.
 *
 * Le verdict est rendu avec un code HTTP significatif (200 accepte, 409 refus
 * metier, 404 introuvable) **et** un code applicatif stable. L'agent lit le
 * libelle, la tablette lit le code : elle peut ainsi declencher un signal
 * sonore different selon qu'il s'agit d'un billet deja utilise ou d'une erreur
 * de bus, sans analyser une phrase en francais.
 *
 * @mixin ScanResult
 */
class ScanResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ScanResult $result */
        $result = $this->resource;

        return [
            'accepted' => $result->isAccepted(),
            'outcome' => $result->outcome->value,
            'outcome_label' => $result->outcome->label(),
            'is_suspicious' => $result->outcome->isSuspicious(),
            // Vrai quand la tablette rejoue un scan deja enregistre hors
            // ligne : ce n'est pas une seconde tentative, et l'agent ne doit
            // pas le lire comme telle.
            'is_replay' => $result->isReplay,
            'previously_scanned_at' => $result->previouslyScannedAt?->toIso8601String(),
            'ticket' => $result->ticket !== null ? new TicketResource($result->ticket) : null,
        ];
    }
}
