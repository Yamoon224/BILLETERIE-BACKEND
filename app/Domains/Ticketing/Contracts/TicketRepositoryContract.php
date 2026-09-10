<?php

namespace App\Domains\Ticketing\Contracts;

use App\Domains\Ticketing\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TicketRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByCode(string $code): ?Ticket;

    /**
     * Charge un billet en le verrouillant jusqu'a la fin de la transaction.
     *
     * C'est la pierre angulaire du refus de double scan. Deux tablettes qui
     * presentent le meme billet a la meme seconde entrent toutes deux dans la
     * transaction ; le verrou de ligne les serialise, et la seconde lit un
     * billet deja marque comme utilise. Sans lui, les deux liraient
     * `scanned_at = null` et laisseraient monter deux personnes.
     *
     * A n'appeler qu'a l'interieur d'une transaction : hors transaction, le
     * verrou est relache immediatement et ne protege rien.
     */
    public function lockByCodeForUpdate(string $code): ?Ticket;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Ticket;

    /** Marque le billet comme embarque. */
    public function markScanned(
        Ticket $ticket,
        ?string $scannedByUserId,
        ?string $stationId,
        ?string $scanClientReference,
    ): Ticket;

    /**
     * Retire le billet de la circulation et **libere sa place**.
     *
     * La place liberee est deplacee dans `released_seat_number` et
     * `seat_number` passe a NULL : c'est ce qui rend la place a la vente sans
     * renoncer a l'index unique qui empeche de la vendre deux fois. Voir la
     * migration des billets.
     */
    public function releaseSeat(Ticket $ticket, TicketStatus $status): Ticket;

    /**
     * Billets d'un depart, pour la liste d'embarquement de l'agent.
     *
     * @return list<Ticket>
     */
    public function forTrip(string $tripId): array;
}
