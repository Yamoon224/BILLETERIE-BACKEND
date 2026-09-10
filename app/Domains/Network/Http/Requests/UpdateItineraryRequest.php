<?php

namespace App\Domains\Network\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItineraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Les villes ne sont pas modifiables apres creation.
     *
     * Changer l'origine d'un itineraire deja exploite reecrirait le trajet de
     * departs deja vendus : le voyageur a achete « Abidjan vers Bouake », et
     * ce n'est pas un champ que l'on corrige. Une liaison erronee se
     * desactive, et la bonne se cree.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'distance_km' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:2880'],
            'base_price' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
