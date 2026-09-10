<?php

namespace App\Domains\Payments\Contracts;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Payment>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Payment;

    /**
     * Retrouve un encaissement par la reference rendue par l'agregateur.
     *
     * C'est la seule cle dont dispose un rappel entrant : l'agregateur ne
     * connait pas nos identifiants internes, et lui en confier un reviendrait
     * a accepter qu'un tiers designe nos lignes de recette.
     */
    public function findByExternalReference(string $externalReference): ?Payment;

    public function findByClientReference(string $clientReference): ?Payment;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Payment;

    /** @param  array<string, mixed>  $attributes */
    public function update(Payment $payment, array $attributes): Payment;

    /**
     * Charge un encaissement en le verrouillant jusqu'a la fin de la
     * transaction. Les agregateurs rejouent volontiers leurs rappels : sans
     * verrou, deux rappels simultanes confirmeraient deux fois la meme vente.
     */
    public function lockByExternalReferenceForUpdate(string $externalReference): ?Payment;
}
