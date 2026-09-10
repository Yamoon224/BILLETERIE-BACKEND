<?php

namespace App\Domains\Ticketing\Repositories;

use App\Domains\Ticketing\Contracts\OccupiedSeatReaderContract;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Lecture des places occupees.
 *
 * La definition d'« occupee » tient dans une clause : un billet dont
 * `seat_number` n'est pas nul. Elle n'a pas besoin de consulter le statut,
 * parce que l'annulation vide deja la colonne (voir
 * `TicketRepositoryContract::releaseSeat`). Faire porter la regle par la
 * donnee plutot que par une condition applicative garantit que l'index unique
 * `(trip_id, seat_number)` et cette lecture disent exactement la meme chose —
 * s'ils divergeaient, l'un vendrait une place que l'autre affiche libre.
 */
final class EloquentOccupiedSeatReader implements OccupiedSeatReaderContract
{
    /** @return list<string> */
    public function occupiedSeatNumbers(string $tripId): array
    {
        return Ticket::query()
            ->where('trip_id', $tripId)
            ->whereNotNull('seat_number')
            ->pluck('seat_number')
            ->map(fn ($seat) => (string) $seat)
            ->values()
            ->all();
    }

    /** @return array<string, int> */
    public function occupiedSeatCounts(array $tripIds): array
    {
        if ($tripIds === []) {
            return [];
        }

        /** @var array<string, int> $counts */
        $counts = Ticket::query()
            ->select('trip_id', DB::raw('COUNT(*) as occupied'))
            ->whereIn('trip_id', $tripIds)
            ->whereNotNull('seat_number')
            ->groupBy('trip_id')
            ->pluck('occupied', 'trip_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        // Un depart sans aucun billet n'apparait pas dans le groupement : on
        // complete a zero pour que l'appelant n'ait pas a distinguer « aucune
        // place prise » de « depart inconnu ».
        foreach ($tripIds as $tripId) {
            $counts[$tripId] ??= 0;
        }

        return $counts;
    }
}
