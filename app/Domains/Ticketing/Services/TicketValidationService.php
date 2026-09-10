<?php

namespace App\Domains\Ticketing\Services;

use App\Domains\Shared\Support\Reference;
use App\Domains\Ticketing\Contracts\TicketRepositoryContract;
use App\Domains\Ticketing\Contracts\TicketSignerContract;
use App\Domains\Ticketing\DTOs\ScanResult;
use App\Domains\Ticketing\DTOs\TicketQrPayload;
use App\Domains\Ticketing\Enums\ScanOutcome;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Controle des billets a l'embarquement.
 *
 * C'est le service qui porte le critere de recette le plus explicite du cahier
 * des charges : « un billet scanne une premiere fois est refuse lors d'une
 * seconde tentative de scan ». Trois decisions le garantissent.
 *
 * **1. La decision se prend sous verrou de ligne.** Une gare fait embarquer
 * deux bus voisins avec deux tablettes ; rien n'empeche le meme billet d'etre
 * presente aux deux a la meme seconde, volontairement ou non. Lire l'etat puis
 * ecrire laisserait une fenetre entre les deux. Ici, la lecture est
 * verrouillante et la fenetre n'existe pas.
 *
 * **2. Le rejeu hors ligne n'est pas une fraude.** L'application agent
 * enregistre ses scans sans reseau et les rejoue au retour de la connexion,
 * parfois plusieurs fois si la reponse se perd. Un scan qui revient avec la
 * *meme reference client* que celui deja enregistre est le meme geste, pas un
 * second : il est accepte comme rejeu. Sans cette distinction, chaque
 * synchronisation transformerait les embarquements de la matinee en tentatives
 * de fraude.
 *
 * **3. La signature ne prouve pas la fraicheur.** Elle prouve que le billet a
 * ete emis par la plateforme. Qu'il n'ait pas deja servi ne se lit qu'en base.
 * Les deux verifications sont donc faites, dans cet ordre, et jamais l'une a
 * la place de l'autre.
 */
final class TicketValidationService
{
    public function __construct(
        private readonly TicketRepositoryContract $tickets,
        private readonly TicketSignerContract $signer,
    ) {}

    /**
     * Valide un billet presente a l'embarquement.
     *
     * @param  string  $scanned  contenu du QR code, ou code du billet saisi a la main
     * @param  string|null  $expectedTripId  depart devant lequel se tient l'agent, s'il est connu
     * @param  string|null  $clientReference  identifiant du geste cote tablette, pour le rejeu
     */
    public function validate(
        string $scanned,
        ?string $expectedTripId = null,
        ?string $agentUserId = null,
        ?string $stationId = null,
        ?string $clientReference = null,
        ?Carbon $now = null,
    ): ScanResult {
        $now ??= Carbon::now();

        $reading = $this->read($scanned);

        if ($reading === null) {
            return ScanResult::refused(ScanOutcome::InvalidSignature);
        }

        [$code, $signatureVerified] = $reading;

        // La transaction enveloppe lecture verrouillante ET ecriture : c'est
        // l'ensemble qui doit etre atomique, pas chacune de ses moities.
        return DB::transaction(function () use (
            $code,
            $signatureVerified,
            $expectedTripId,
            $agentUserId,
            $stationId,
            $clientReference,
            $now,
        ): ScanResult {
            $ticket = $this->tickets->lockByCodeForUpdate($code);

            if ($ticket === null) {
                return ScanResult::refused(ScanOutcome::NotFound);
            }

            if (! $signatureVerified) {
                return ScanResult::refused(ScanOutcome::InvalidSignature, $ticket);
            }

            $replay = $this->refusalBeforeScan($ticket, $expectedTripId, $clientReference, $now);

            if ($replay !== null) {
                return $replay;
            }

            $this->tickets->markScanned($ticket, $agentUserId, $stationId, $clientReference);

            return ScanResult::accepted($ticket);
        });
    }

    /**
     * Toutes les raisons de ne pas laisser monter, dans l'ordre ou elles
     * comptent pour l'agent. `null` signifie « rien ne s'y oppose ».
     */
    private function refusalBeforeScan(
        Ticket $ticket,
        ?string $expectedTripId,
        ?string $clientReference,
        Carbon $now,
    ): ?ScanResult {
        // Rejeu du meme geste hors ligne : on renvoie l'acceptation d'origine.
        if (
            $ticket->isScanned()
            && $clientReference !== null
            && $ticket->scan_client_reference === $clientReference
        ) {
            return ScanResult::accepted($ticket, isReplay: true);
        }

        // Le critere de recette. Il vient avant tous les autres refus : un
        // billet deja utilise doit etre annonce comme tel, meme s'il est aussi
        // presente au mauvais bus.
        if ($ticket->isScanned()) {
            return ScanResult::alreadyUsed($ticket);
        }

        if (! $ticket->status->isBoardable()) {
            return ScanResult::refused(
                $ticket->status === TicketStatus::Used ? ScanOutcome::AlreadyUsed : ScanOutcome::Cancelled,
                $ticket,
            );
        }

        if ($expectedTripId !== null && $ticket->trip_id !== $expectedTripId) {
            return ScanResult::refused(ScanOutcome::WrongTrip, $ticket);
        }

        $trip = $ticket->trip;

        if (! $trip->status->acceptsBoarding()) {
            return ScanResult::refused(ScanOutcome::TripCancelled, $ticket);
        }

        if (! $trip->isWithinBoardingWindow($now)) {
            return ScanResult::refused(ScanOutcome::OutsideBoardingWindow, $ticket);
        }

        return null;
    }

    /**
     * Lit ce que l'agent a soumis.
     *
     * Deux formes sont acceptees. Le contenu d'un QR code, qui porte sa
     * signature et se verifie. Et le code du billet seul, tape a la main quand
     * le QR est illisible — cas frequent avec un ticket thermique defraichi.
     * La saisie manuelle ne peut evidemment pas etre verifiee
     * cryptographiquement : elle est acceptee parce que le code lui-meme est
     * imprevisible (voir Reference) et que le billet est ensuite confronte a
     * la base, qui reste l'autorite.
     *
     * @return array{0: string, 1: bool}|null code du billet et verdict de signature
     */
    private function read(string $scanned): ?array
    {
        $scanned = trim($scanned);

        if (! str_starts_with($scanned, TicketQrPayload::PREFIX.'|')) {
            return [Reference::normalize($scanned), true];
        }

        try {
            $decoded = TicketQrPayload::decode($scanned);
        } catch (InvalidArgumentException) {
            return null;
        }

        /** @var TicketQrPayload $payload */
        $payload = $decoded['payload'];

        return [
            $payload->ticketCode,
            $this->signer->verify($payload, $decoded['signature'], $decoded['key_version']),
        ];
    }
}
