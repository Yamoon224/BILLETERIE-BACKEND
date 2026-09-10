<?php

namespace App\Domains\Network\Http\Requests;

use App\Domains\Network\Enums\VehicleClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
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
            'registration' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'registration')],
            'model' => ['nullable', 'string', 'max:255'],
            'class' => ['required', Rule::in(VehicleClass::values())],
            // Bornes issues du parc reel : un minibus commence a 12 places, un
            // grand car double etage plafonne autour de 90.
            'seat_capacity' => ['required', 'integer', 'min:4', 'max:120'],
            // 4 pour un plan 2+2, 5 pour un 3+2. Au-dela, ce n'est plus un car.
            'seats_per_row' => ['required', 'integer', 'min:2', 'max:6'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
