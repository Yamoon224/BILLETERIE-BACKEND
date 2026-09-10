<?php

namespace App\Domains\Network\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Company */
class CompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'logo_path' => $this->logo_path,
            'commission_per_mille' => $this->commission_per_mille,
            // Le taux applique reellement, `null` resolu : l'interface affiche
            // ce qui sera facture, pas ce qui est stocke.
            'effective_commission_per_mille' => $this->effectiveCommissionPerMille(),
            'is_active' => $this->is_active,
            'vehicles_count' => $this->whenCounted('vehicles'),
            'itineraries_count' => $this->whenCounted('itineraries'),
            'stations_count' => $this->whenCounted('stations'),
            'trips_count' => $this->whenCounted('trips'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
