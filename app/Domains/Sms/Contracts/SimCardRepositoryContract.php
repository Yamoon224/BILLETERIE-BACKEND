<?php

namespace App\Domains\Sms\Contracts;

use App\Domains\Sms\Enums\SimOperator;
use App\Models\SimCard;
use Illuminate\Support\Collection;

interface SimCardRepositoryContract
{
    /** @return Collection<int, SimCard> */
    public function all(): Collection;

    public function findOrFail(string $id): SimCard;

    /** @param  array<string, mixed>  $attributes */
    public function update(SimCard $simCard, array $attributes): SimCard;

    /** Carte active de cet operateur, s'il y en a une. */
    public function findActiveByOperator(SimOperator $operator): ?SimCard;
}
