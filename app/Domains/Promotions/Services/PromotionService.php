<?php

namespace App\Domains\Promotions\Services;

use App\Domains\Promotions\Contracts\PromotionRepositoryContract;
use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PromotionService
{
    public function __construct(private readonly PromotionRepositoryContract $promotions) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Promotion>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->promotions->paginate($filters, $perPage);
    }

    public function find(string $id): Promotion
    {
        return $this->promotions->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Promotion
    {
        return $this->promotions->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Promotion $promotion, array $data): Promotion
    {
        return $this->promotions->update($promotion, $data);
    }

    public function delete(Promotion $promotion): void
    {
        $this->promotions->delete($promotion);
    }
}
