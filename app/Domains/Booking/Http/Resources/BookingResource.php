<?php

namespace App\Domains\Booking\Http\Resources;

use App\Domains\Payments\Http\Resources\PaymentResource;
use App\Domains\Scheduling\Http\Resources\TripResource;
use App\Domains\Ticketing\Http\Resources\TicketResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),

            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,

            'seats_count' => $this->seats_count,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'commission_amount' => $this->commission_amount,
            'commission_per_mille' => $this->commission_per_mille,
            'net_amount' => $this->netAmount(),

            'expires_at' => $this->expires_at?->toIso8601String(),
            // Calcule plutot que deduit du statut cote client : entre deux
            // passages de la commande d'expiration, une reservation peut etre
            // encore `pending` alors que son delai est ecoule. L'interface doit
            // le savoir avant de proposer un paiement qui sera refuse.
            'is_hold_expired' => $this->isHoldExpired(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,

            'sold_offline_at' => $this->sold_offline_at?->toIso8601String(),
            'synced_at' => $this->synced_at?->toIso8601String(),
            'client_reference' => $this->client_reference,
            'notes' => $this->notes,

            'trip' => $this->whenLoaded('trip', fn () => new TripResource($this->trip)),
            'trip_id' => $this->trip_id,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'company_id' => $this->company_id,
            'sold_by' => $this->whenLoaded('soldBy', fn () => $this->soldBy ? [
                'id' => $this->soldBy->id,
                'name' => $this->soldBy->name,
            ] : null),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
            'tickets_count' => $this->whenCounted('tickets'),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
