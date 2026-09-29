<?php

namespace App\Domains\Sms\Http\Resources;

use App\Models\SimCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SimCard */
class SimCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operator' => $this->operator->value,
            'operator_label' => $this->operator->label(),
            'phone_number' => $this->phone_number,
            'balance' => $this->balance,
            'low_balance_threshold' => $this->low_balance_threshold,
            'is_low_balance' => $this->isLowBalance(),
            'is_active' => $this->is_active,
        ];
    }
}
