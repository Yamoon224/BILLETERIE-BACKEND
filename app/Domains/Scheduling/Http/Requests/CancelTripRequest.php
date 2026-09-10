<?php

namespace App\Domains\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Le motif est obligatoire : il est repris tel quel dans le message
            // envoye aux voyageurs deja inscrits, et « annule » sans raison
            // fait basculer un mecontentement en reclamation.
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
