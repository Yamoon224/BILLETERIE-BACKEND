<?php

namespace App\Domains\CarRental\Http\Requests;

use App\Domains\CarRental\Enums\FuelType;
use App\Domains\CarRental\Enums\RentalVehicleCategory;
use App\Domains\CarRental\Enums\TransmissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRentalVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'partner_id' => ['required', 'uuid', 'exists:partners,id'],
            'city_id' => ['required', 'uuid', 'exists:cities,id'],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'category' => ['required', Rule::in(RentalVehicleCategory::values())],
            'transmission' => ['required', Rule::in(TransmissionType::values())],
            'fuel_type' => ['required', Rule::in(FuelType::values())],
            'seats' => ['required', 'integer', 'min:1', 'max:30'],
            'price_per_day' => ['required', 'integer', 'min:1000'],
            'with_driver_available' => ['nullable', 'boolean'],
            'plate_number' => ['nullable', 'string', 'max:20', Rule::unique('rental_vehicles', 'plate_number')],
            'cover_photo_url' => ['nullable', 'string', 'max:2048'],
            'photo_urls' => ['nullable', 'array'],
            'photo_urls.*' => ['string', 'max:2048'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
