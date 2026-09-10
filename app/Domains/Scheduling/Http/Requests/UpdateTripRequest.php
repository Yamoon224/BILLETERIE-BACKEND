<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Scheduling\Enums\TripStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ni `price` ni `vehicle_id` ne sont refuses ici : ils le sont par le
     * service, et seulement si des places ont deja ete vendues. Un depart sans
     * aucune reservation reste librement corrigeable — c'est le cas d'une
     * erreur de saisie reperee dans la minute.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'departure_station_id' => ['sometimes', 'uuid', 'exists:stations,id'],
            'arrival_station_id' => ['sometimes', 'uuid', 'exists:stations,id'],
            'departs_at' => ['sometimes', 'date'],
            'arrives_at' => ['nullable', 'date'],
            'price' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'vehicle_id' => ['sometimes', 'uuid', 'exists:vehicles,id'],
            'status' => ['sometimes', Rule::in(TripStatus::values())],
        ];
    }
}
