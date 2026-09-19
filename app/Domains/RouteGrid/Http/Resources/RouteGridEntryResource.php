<?php

namespace App\Domains\RouteGrid\Http\Resources;

use App\Models\RouteGridEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RouteGridEntry */
class RouteGridEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'origin_city_id' => $this->origin_city_id,
            'destination_city_id' => $this->destination_city_id,
            'origin_city' => $this->whenLoaded('originCity', fn () => [
                'id' => $this->originCity->id,
                'name' => $this->originCity->name,
                'slug' => $this->originCity->slug,
            ]),
            'destination_city' => $this->whenLoaded('destinationCity', fn () => [
                'id' => $this->destinationCity->id,
                'name' => $this->destinationCity->name,
                'slug' => $this->destinationCity->slug,
            ]),
            'company_name' => $this->company_name,
            'price' => $this->price,
            'currency' => (string) config('ticketing.currency'),
            'distance_km' => $this->distance_km,
            'duration_minutes' => $this->duration_minutes,
            'departure_times' => $this->departure_times ?? [],
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
