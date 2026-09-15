<?php

namespace App\Domains\Housing\Http\Requests;

use App\Domains\Housing\Enums\ApartmentAmenity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'city_id' => ['sometimes', 'uuid', 'exists:cities,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'bedrooms' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'bathrooms' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:40'],
            'price_per_night' => ['sometimes', 'integer', 'min:1000'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => [Rule::in(ApartmentAmenity::values())],
            'cover_photo_url' => ['nullable', 'string', 'max:2048'],
            'photo_urls' => ['nullable', 'array'],
            'photo_urls.*' => ['string', 'max:2048'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
