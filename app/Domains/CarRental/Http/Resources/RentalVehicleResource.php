<?php

namespace App\Domains\CarRental\Http\Resources;

use App\Models\RentalVehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RentalVehicle */
class RentalVehicleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'transmission' => $this->transmission->value,
            'transmission_label' => $this->transmission->label(),
            'fuel_type' => $this->fuel_type->value,
            'fuel_type_label' => $this->fuel_type->label(),
            'seats' => $this->seats,
            'price_per_day' => $this->price_per_day,
            'currency' => 'XOF',
            'with_driver_available' => $this->with_driver_available,
            'plate_number' => $this->plate_number,
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
