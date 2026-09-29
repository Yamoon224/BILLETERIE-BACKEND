<?php

namespace App\Domains\Sms\Contracts;

use App\Models\NotificationDispatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SmsQueueRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, NotificationDispatch>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Indicateurs de supervision du jour.
     *
     * @return array{sent_today: int, failed_today: int, delivery_rate: float, queued: int}
     */
    public function stats(): array;
}
