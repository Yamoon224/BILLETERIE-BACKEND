<?php

namespace App\Domains\Sms\Services;

use App\Domains\Sms\Contracts\SimCardRepositoryContract;
use App\Domains\Sms\Contracts\SmsQueueRepositoryContract;
use App\Models\NotificationDispatch;
use App\Models\SimCard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Supervision du SMS Box : cartes SIM et file d'envoi des billets.
 */
final class SmsBoxService
{
    public function __construct(
        private readonly SimCardRepositoryContract $simCards,
        private readonly SmsQueueRepositoryContract $queue,
    ) {}

    /** @return Collection<int, SimCard> */
    public function simCards(): Collection
    {
        return $this->simCards->all();
    }

    /** @param  array<string, mixed>  $data */
    public function updateSimCard(SimCard $simCard, array $data): SimCard
    {
        return $this->simCards->update($simCard, $data);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, NotificationDispatch>
     */
    public function queue(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->queue->paginate($filters, $perPage);
    }

    /** @return array{sent_today: int, failed_today: int, delivery_rate: float, queued: int} */
    public function stats(): array
    {
        return $this->queue->stats();
    }
}
