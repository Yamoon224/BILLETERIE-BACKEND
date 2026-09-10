<?php

namespace App\Domains\Ticketing\Http\Controllers;

use App\Domains\Ticketing\Http\Requests\ValidateTicketRequest;
use App\Domains\Ticketing\Http\Resources\ScanResultResource;
use App\Domains\Ticketing\Services\TicketValidationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Controle des billets a l'embarquement.
 *
 * Le verdict est rendu **dans le corps de la reponse**, avec un code HTTP qui
 * en reflete la nature : 200 pour un embarquement autorise, 409 pour un refus
 * metier, 404 pour un billet inconnu.
 *
 * Un refus n'est pas une erreur du client : la requete etait valide et a bien
 * ete traitee. C'est pourquoi il ne passe pas par le handler d'exceptions mais
 * par une reponse ordinaire — l'agent doit voir le billet, le nom du voyageur
 * et l'heure du premier scan, informations qu'un message d'erreur nu ne
 * porterait pas.
 */
class TicketValidationController extends Controller
{
    public function __construct(private readonly TicketValidationService $validation) {}

    public function __invoke(ValidateTicketRequest $request): JsonResponse
    {
        $result = $this->validation->validate(
            scanned: (string) $request->input('code'),
            expectedTripId: $request->input('trip_id'),
            agentUserId: $request->user()?->id,
            stationId: $request->input('station_id'),
            clientReference: $request->input('client_reference'),
        );

        return response()->json(
            ['data' => new ScanResultResource($result)],
            $result->outcome->httpStatus(),
        );
    }
}
