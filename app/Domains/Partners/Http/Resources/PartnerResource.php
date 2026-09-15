<?php

namespace App\Domains\Partners\Http\Resources;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Partner */
class PartnerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'logo_path' => $this->logo_path,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'apartments_count' => $this->whenCounted('apartments'),
            'rental_vehicles_count' => $this->whenCounted('rentalVehicles'),
            'city' => $this->whenLoaded('city', fn () => $this->city === null ? null : [
                'id' => $this->city->id,
                'name' => $this->city->name,
                'slug' => $this->city->slug,
            ]),
            'city_id' => $this->city_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
