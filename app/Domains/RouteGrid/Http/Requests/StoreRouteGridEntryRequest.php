<?php

namespace App\Domains\RouteGrid\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRouteGridEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'origin_city_id' => ['required', 'uuid', 'exists:cities,id'],
            'destination_city_id' => ['required', 'uuid', 'exists:cities,id', 'different:origin_city_id'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'distance_km' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:4320'],
            'departure_times' => ['nullable', 'array', 'max:24'],
            'departure_times.*' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'destination_city_id.different' => 'La ville d arrivee doit differer de la ville de depart.',
        ];
    }
}
