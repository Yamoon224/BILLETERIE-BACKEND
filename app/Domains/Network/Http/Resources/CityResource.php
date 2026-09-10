<?php

namespace App\Domains\Network\Http\Resources;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin City */
class CityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'region' => $this->region,
            'is_active' => $this->is_active,
            'stations_count' => $this->whenCounted('stations'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
