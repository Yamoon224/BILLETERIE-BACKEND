<?php

namespace App\Domains\Sms\Repositories;

use App\Domains\Sms\Contracts\SimCardRepositoryContract;
use App\Domains\Sms\Enums\SimOperator;
use App\Models\SimCard;
use Illuminate\Support\Collection;

final class EloquentSimCardRepository implements SimCardRepositoryContract
{
    /** @return Collection<int, SimCard> */
    public function all(): Collection
    {
        return SimCard::query()->orderBy('operator')->get();
    }

    public function findOrFail(string $id): SimCard
    {
        return SimCard::query()->findOrFail($id);
    }

    public function update(SimCard $simCard, array $attributes): SimCard
    {
        $simCard->update($attributes);

        return $simCard->refresh();
    }

    public function findActiveByOperator(SimOperator $operator): ?SimCard
    {
        return SimCard::query()->where('operator', $operator)->where('is_active', true)->first();
    }
}
