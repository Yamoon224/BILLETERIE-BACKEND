<?php

namespace App\Domains\Network\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vehicle */
class VehicleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration' => $this->registration,
            'model' => $this->model,
            'class' => $this->class->value,
            'class_label' => $this->class->label(),
            'seat_capacity' => $this->seat_capacity,
            'seats_per_row' => $this->seats_per_row,
            // Le plan est derive, pas stocke : l'exposer evite au frontend de
            // reimplementer la regle de numerotation des places.
            'seat_rows' => $this->seatMap()->rows(),
            'is_active' => $this->is_active,
            'trips_count' => $this->whenCounted('trips'),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'company_id' => $this->company_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
