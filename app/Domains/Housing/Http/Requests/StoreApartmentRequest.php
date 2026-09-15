<?php

namespace App\Domains\Housing\Http\Requests;

use App\Domains\Housing\Enums\ApartmentAmenity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApartmentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:20'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:20'],
            'capacity' => ['required', 'integer', 'min:1', 'max:40'],
            'price_per_night' => ['required', 'integer', 'min:1000'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => [Rule::in(ApartmentAmenity::values())],
            'cover_photo_url' => ['nullable', 'string', 'max:2048'],
            'photo_urls' => ['nullable', 'array'],
            'photo_urls.*' => ['string', 'max:2048'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
