<?php

namespace App\Domains\Partners\Contracts;

use App\Models\Partner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PartnerRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Partner>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Partner;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Partner;

    /** @param  array<string, mixed>  $attributes */
    public function update(Partner $partner, array $attributes): Partner;

    public function delete(Partner $partner): void;

    public function countDependents(Partner $partner): int;
}
