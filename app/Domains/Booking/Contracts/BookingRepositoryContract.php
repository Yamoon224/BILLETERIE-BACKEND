<?php

namespace App\Domains\Booking\Contracts;

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

interface BookingRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Booking>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Booking;

    public function findByReference(string $reference): ?Booking;

    /**
     * Retrouve une vente deja enregistree sous cette reference client.
     *
     * C'est le pivot de l'idempotence hors ligne : une tablette qui rejoue son
     * envoi doit retrouver la vente creee au premier passage, et non en creer
     * une seconde. Sans cette lecture, une reponse perdue sur un reseau
     * intermittent vendrait deux fois la meme place.
     */
    public function findByClientReference(string $clientReference): ?Booking;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Booking;

    /** @param  array<string, mixed>  $attributes */
    public function update(Booking $booking, array $attributes): Booking;

    /**
     * Reservations en attente dont le delai de paiement est ecoule.
     *
     * Renvoyees par lots : la commande d'expiration tourne toutes les minutes
     * et ne doit pas charger en memoire l'integralite d'un arriere qui aurait
     * grossi pendant une interruption de service.
     *
     * @return list<Booking>
     */
    public function expiredHolds(Carbon $now, int $limit = 200): array;
}
