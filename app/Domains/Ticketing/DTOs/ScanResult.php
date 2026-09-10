<?php

namespace App\Domains\Ticketing\DTOs;

use App\Domains\Ticketing\Enums\ScanOutcome;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * Verdict rendu par un scan a l'embarquement.
 *
 * Un objet plutot qu'une exception, y compris pour les refus : un refus de
 * scan n'est pas un incident technique, c'est une reponse metier ordinaire que
 * l'agent doit lire, comprendre et — dans le cas d'un billet deja utilise —
 * montrer au voyageur. Le faire remonter comme exception obligerait a
 * intercepter huit types differents pour reconstruire la meme information.
 *
 * `previouslyScannedAt` est le champ qui compte devant la porte du bus : « ce
 * billet a deja embarque a 06h12 » clot une discussion que « billet refuse »
 * ouvrirait.
 */
final readonly class ScanResult
{
    private function __construct(
        public ScanOutcome $outcome,
        public ?Ticket $ticket,
        public ?Carbon $previouslyScannedAt = null,
        /** Vrai quand le scan etait deja enregistre sous la meme reference client. */
        public bool $isReplay = false,
    ) {}

    public static function accepted(Ticket $ticket, bool $isReplay = false): self
    {
        return new self(ScanOutcome::Accepted, $ticket, isReplay: $isReplay);
    }

    public static function alreadyUsed(Ticket $ticket): self
    {
        return new self(ScanOutcome::AlreadyUsed, $ticket, previouslyScannedAt: $ticket->scanned_at);
    }

    public static function refused(ScanOutcome $outcome, ?Ticket $ticket = null): self
    {
        return new self($outcome, $ticket);
    }

    public function isAccepted(): bool
    {
        return $this->outcome->isAccepted();
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return array_filter([
            'ticket_code' => $this->ticket?->code,
            'seat_number' => $this->ticket?->displaySeatNumber(),
            'passenger_name' => $this->ticket?->passenger_name,
            'previously_scanned_at' => $this->previouslyScannedAt?->toIso8601String(),
        ], static fn ($value) => $value !== null);
    }
}
