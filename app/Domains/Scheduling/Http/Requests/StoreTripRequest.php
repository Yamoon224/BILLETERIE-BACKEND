<?php

namespace App\Domains\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'itinerary_id' => ['required', 'uuid', 'exists:itineraries,id'],
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'departure_station_id' => ['required', 'uuid', 'exists:stations,id'],
            'arrival_station_id' => ['required', 'uuid', 'exists:stations,id', 'different:departure_station_id'],
            // Un depart se programme a l'avance, jamais dans le passe : une
            // date passee produirait un depart invendable dont personne ne
            // comprendrait pourquoi il n'apparait pas a la recherche.
            'departs_at' => ['required', 'date', 'after:now'],
            'arrives_at' => ['nullable', 'date', 'after:departs_at'],
            // Facultatif : a defaut, le tarif de reference de l'itineraire.
            'price' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'departs_at.after' => 'L heure de depart doit etre dans le futur.',
            'arrival_station_id.different' => 'La gare d arrivee doit differer de la gare de depart.',
        ];
    }
}
