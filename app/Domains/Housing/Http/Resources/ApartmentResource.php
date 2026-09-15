<?php

namespace App\Domains\Housing\Http\Resources;

use App\Models\Apartment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Apartment */
class ApartmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'neighborhood' => $this->neighborhood,
            'address_line' => $this->address_line,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'capacity' => $this->capacity,
            'price_per_night' => $this->price_per_night,
            'currency' => 'XOF',
            'amenities' => $this->amenities ?? [],
            'cover_photo_url' => $this->cover_photo_url,
            'photo_urls' => $this->photo_urls ?? [],
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $this->city->name,
                'slug' => $this->city->slug,
            ]),
            'city_id' => $this->city_id,
            'partner' => $this->whenLoaded('partner', fn () => [
                'id' => $this->partner->id,
                'name' => $this->partner->name,
                'phone' => $this->partner->phone,
                'whatsapp' => $this->partner->whatsapp,
            ]),
            'partner_id' => $this->partner_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
