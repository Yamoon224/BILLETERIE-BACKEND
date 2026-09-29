<?php

namespace App\Domains\Promotions\Repositories;

use App\Domains\Promotions\Contracts\PromotionRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentPromotionRepository implements PromotionRepositoryContract
{
    /** @var array<string, string|array{0: string, 1: string}> */
    private const SORTABLE = [
        'title' => 'title',
        'zone' => 'zone',
        'is_active' => 'is_active',
        'created_at' => 'created_at',
    ];

    /** @return LengthAwarePaginator<int, Promotion> */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Promotion::query()
            ->when($filters['zone'] ?? null, fn ($query, $zone) => $query->where('zone', $zone))
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'created_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Promotion
    {
        return Promotion::query()->findOrFail($id);
    }

    public function create(array $attributes): Promotion
    {
        return Promotion::create($attributes);
    }

    public function update(Promotion $promotion, array $attributes): Promotion
    {
        $promotion->update($attributes);

        return $promotion->refresh();
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }
}
