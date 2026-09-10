<?php

namespace App\Domains\Network\Http\Resources;

use App\Models\Itinerary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Itinerary */
class ItineraryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distance_km' => $this->distance_km,
            'duration_minutes' => $this->duration_minutes,
            'base_price' => $this->base_price,
            'currency' => (string) config('ticketing.currency'),
            'is_active' => $this->is_active,
            'trips_count' => $this->whenCounted('trips'),
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
            'origin_city_id' => $this->origin_city_id,
            'destination_city_id' => $this->destination_city_id,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'company_id' => $this->company_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
