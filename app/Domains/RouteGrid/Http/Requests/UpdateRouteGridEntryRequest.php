<?php

namespace App\Domains\RouteGrid\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRouteGridEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Les villes ne se modifient pas : une ligne saisie pour la mauvaise
     * liaison se supprime et se recree, comme pour un itineraire.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:120'],
            'price' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'distance_km' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:4320'],
            'departure_times' => ['nullable', 'array', 'max:24'],
            'departure_times.*' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
