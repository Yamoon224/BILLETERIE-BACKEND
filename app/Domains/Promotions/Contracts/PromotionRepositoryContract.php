<?php

namespace App\Domains\Promotions\Contracts;

use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PromotionRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Promotion>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Promotion;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Promotion;

    /** @param  array<string, mixed>  $attributes */
    public function update(Promotion $promotion, array $attributes): Promotion;

    public function delete(Promotion $promotion): void;
}
