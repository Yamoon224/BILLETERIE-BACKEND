<?php

namespace App\Domains\Network\Http\Requests;

use App\Domains\Network\Enums\VehicleClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $vehicleId = $this->route('vehicle')?->id;

        return [
            'registration' => [
                'sometimes', 'string', 'max:20',
                Rule::unique('vehicles', 'registration')->ignore($vehicleId),
            ],
            'model' => ['nullable', 'string', 'max:255'],
            'class' => ['sometimes', Rule::in(VehicleClass::values())],
            'seat_capacity' => ['sometimes', 'integer', 'min:4', 'max:120'],
            'seats_per_row' => ['sometimes', 'integer', 'min:2', 'max:6'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
