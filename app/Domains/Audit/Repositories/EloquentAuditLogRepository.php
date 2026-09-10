<?php

namespace App\Domains\Audit\Repositories;

use App\Domains\Audit\Contracts\AuditLogRepositoryContract;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentAuditLogRepository implements AuditLogRepositoryContract
{
    /** @return LengthAwarePaginator<int, ActivityLog> */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->with('causer:id,name')
            ->when($filters['subject_type'] ?? null, fn ($query, $type) => $query->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn ($query, $id) => $query->where('subject_id', $id))
            ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($filters['causer_id'] ?? null, fn ($query, $id) => $query->where('causer_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('description', 'like', "%{$search}%"))
            // Un journal se lit du plus recent au plus ancien : c'est
            // l'evenement de tout a l'heure qu'on vient chercher.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array{subject_types: list<string>, events: list<string>} */
    public function facets(): array
    {
        return [
            'subject_types' => ActivityLog::query()
                ->whereNotNull('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->map(fn ($type) => (string) $type)
                ->values()
                ->all(),
            'events' => ActivityLog::query()
                ->whereNotNull('event')
                ->distinct()
                ->orderBy('event')
                ->pluck('event')
                ->map(fn ($event) => (string) $event)
                ->values()
                ->all(),
        ];
    }
}
