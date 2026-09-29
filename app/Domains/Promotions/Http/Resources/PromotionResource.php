<?php

namespace App\Domains\Promotions\Http\Resources;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Promotion */
class PromotionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'zone' => $this->zone->value,
            'zone_label' => $this->zone->label(),
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'advertiser_name' => $this->advertiser_name,
            'partner_id' => $this->partner_id,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
