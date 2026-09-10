<?php

namespace App\Domains\Network\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItineraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'origin_city_id' => ['required', 'uuid', 'exists:cities,id'],
            'destination_city_id' => [
                'required', 'uuid', 'exists:cities,id',
                // Une liaison d'une ville vers elle-meme n'existe pas, et la
                // laisser passer produirait des departs invendables qu'il
                // faudrait ensuite expliquer.
                'different:origin_city_id',
                Rule::unique('itineraries', 'destination_city_id')
                    ->where('company_id', $this->input('company_id'))
                    ->where('origin_city_id', $this->input('origin_city_id')),
            ],
            'distance_km' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:2880'],
            // Entier : le franc CFA n'a pas de sous-unite en circulation.
            'base_price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'destination_city_id.different' => 'La ville d arrivee doit differer de la ville de depart.',
            'destination_city_id.unique' => 'Cette compagnie exploite deja un itineraire entre ces deux villes.',
        ];
    }
}
