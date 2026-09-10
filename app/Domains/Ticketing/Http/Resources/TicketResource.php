<?php

namespace App\Domains\Ticketing\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un billet.
 *
 * Ni la signature ni la version de cle ne sont exposees. Elles ne servent que
 * dans le QR code, et les publier dans une reponse JSON permettrait de
 * fabriquer un QR valide sans avoir jamais eu le billet en main — ce qui
 * viderait la signature de son role.
 *
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'seat_number' => $this->displaySeatNumber(),
            'is_seat_released' => $this->seat_number === null,
            'passenger_name' => $this->passenger_name,
            'passenger_phone' => $this->passenger_phone,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_scanned' => $this->isScanned(),
            'scanned_at' => $this->scanned_at?->toIso8601String(),
            'scanned_by' => $this->whenLoaded('scannedBy', fn () => $this->scannedBy ? [
                'id' => $this->scannedBy->id,
                'name' => $this->scannedBy->name,
            ] : null),
            'booking_id' => $this->booking_id,
            'booking' => $this->whenLoaded('booking', fn () => [
                'id' => $this->booking->id,
                'reference' => $this->booking->reference,
                'customer_phone' => $this->booking->customer_phone,
            ]),
            'trip_id' => $this->trip_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
