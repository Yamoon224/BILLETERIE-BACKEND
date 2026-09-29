<?php

namespace App\Domains\Sms\Repositories;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Shared\Support\Sort;
use App\Domains\Sms\Contracts\SmsQueueRepositoryContract;
use App\Models\NotificationDispatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentSmsQueueRepository implements SmsQueueRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'created_at' => 'created_at',
        'status' => 'status',
    ];

    /** @return LengthAwarePaginator<int, NotificationDispatch> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return NotificationDispatch::query()
            ->with('simCard:id,operator')
            ->where('channel', NotificationChannel::Sms)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'created_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array{sent_today: int, failed_today: int, delivery_rate: float, queued: int} */
    public function stats(): array
    {
        $today = NotificationDispatch::query()
            ->where('channel', NotificationChannel::Sms)
            ->whereDate('created_at', now())
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $sent = (int) ($today[NotificationStatus::Sent->value] ?? 0);
        $failed = (int) ($today[NotificationStatus::Failed->value] ?? 0);
        $attempted = $sent + $failed;

        return [
            'sent_today' => $sent,
            'failed_today' => $failed,
            // 100 % tant qu'aucun envoi n'a encore ete tente aujourd'hui :
            // un taux a zero laisserait croire a une panne qui n'existe pas.
            'delivery_rate' => $attempted > 0 ? round(($sent / $attempted) * 100, 1) : 100.0,
            'queued' => NotificationDispatch::query()
                ->where('channel', NotificationChannel::Sms)
                ->where('status', NotificationStatus::Pending)
                ->count(),
        ];
    }
}
