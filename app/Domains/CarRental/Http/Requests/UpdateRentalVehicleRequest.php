<?php

namespace App\Domains\CarRental\Http\Requests;

use App\Domains\CarRental\Enums\FuelType;
use App\Domains\CarRental\Enums\RentalVehicleCategory;
use App\Domains\CarRental\Enums\TransmissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRentalVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $vehicleId = $this->route('rentalVehicle')?->id;

        return [
            'city_id' => ['sometimes', 'uuid', 'exists:cities,id'],
            'brand' => ['sometimes', 'string', 'max:255'],
            'model' => ['sometimes', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'category' => ['sometimes', Rule::in(RentalVehicleCategory::values())],
            'transmission' => ['sometimes', Rule::in(TransmissionType::values())],
            'fuel_type' => ['sometimes', Rule::in(FuelType::values())],
            'seats' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'price_per_day' => ['sometimes', 'integer', 'min:1000'],
            'with_driver_available' => ['sometimes', 'boolean'],
            'plate_number' => [
                'nullable', 'string', 'max:20',
                Rule::unique('rental_vehicles', 'plate_number')->ignore($vehicleId),
            ],
            'cover_photo_url' => ['nullable', 'string', 'max:2048'],
            'photo_urls' => ['nullable', 'array'],
            'photo_urls.*' => ['string', 'max:2048'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
