<?php

namespace App\Domains\RouteGrid\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Consultation publique de la grille pour une liaison.
 *
 * Memes noms de parametres que /trips/search : le site relance la meme
 * recherche sur l'un puis sur l'autre selon que la liaison est reservable.
 */
class SearchRouteGridRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'string', 'exists:cities,slug'],
            'destination' => ['required', 'string', 'exists:cities,slug', 'different:origin'],
        ];
    }
}
