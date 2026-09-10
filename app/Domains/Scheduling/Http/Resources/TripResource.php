<?php

namespace App\Domains\Scheduling\Http\Resources;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un depart tel que le voit l'exploitant ou le voyageur.
 *
 * Le nombre de places disponibles n'apparait que s'il a ete calcule en amont
 * et attache au modele (`seats_taken`). Le declencher depuis la ressource
 * produirait une requete par ligne de liste — le probleme N+1, mais en pire :
 * invisible, parce que niche dans la serialisation.
 *
 * @mixin Trip
 */
class TripResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Attribut pose par le service de recherche, jamais par une
        // requete declenchee ici.
        $seatsTaken = $this->getAttribute('seats_taken');

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'departs_at' => $this->departs_at->toIso8601String(),
            'arrives_at' => $this->arrives_at?->toIso8601String(),
            'price' => $this->price,
            'currency' => (string) config('ticketing.currency'),
            'seat_capacity' => $this->seat_capacity,
            'seats_per_row' => $this->seats_per_row,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'accepts_bookings' => $this->status->acceptsBookings(),
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),

            'seats_taken' => $seatsTaken !== null ? (int) $seatsTaken : null,
            'seats_available' => $seatsTaken !== null
                ? max(0, $this->seat_capacity - (int) $seatsTaken)
                : null,

            'itinerary' => $this->whenLoaded('itinerary', fn () => [
                'id' => $this->itinerary->id,
                'duration_minutes' => $this->itinerary->duration_minutes,
                'distance_km' => $this->itinerary->distance_km,
                'origin_city' => $this->itinerary->relationLoaded('originCity') ? [
                    'id' => $this->itinerary->originCity->id,
                    'name' => $this->itinerary->originCity->name,
                    'slug' => $this->itinerary->originCity->slug,
                ] : null,
                'destination_city' => $this->itinerary->relationLoaded('destinationCity') ? [
                    'id' => $this->itinerary->destinationCity->id,
                    'name' => $this->itinerary->destinationCity->name,
                    'slug' => $this->itinerary->destinationCity->slug,
                ] : null,
            ]),
            'itinerary_id' => $this->itinerary_id,

            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle->id,
                'registration' => $this->vehicle->registration,
                'model' => $this->vehicle->model,
                'class' => $this->vehicle->class->value,
                'class_label' => $this->vehicle->class->label(),
            ]),
            'vehicle_id' => $this->vehicle_id,

            'departure_station' => $this->whenLoaded('departureStation', fn () => [
                'id' => $this->departureStation->id,
                'name' => $this->departureStation->name,
                'address' => $this->departureStation->address,
            ]),
            'arrival_station' => $this->whenLoaded('arrivalStation', fn () => [
                'id' => $this->arrivalStation->id,
                'name' => $this->arrivalStation->name,
                'address' => $this->arrivalStation->address,
            ]),
            'departure_station_id' => $this->departure_station_id,
            'arrival_station_id' => $this->arrival_station_id,

            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
                'logo_path' => $this->company->logo_path,
            ]),
            'company_id' => $this->company_id,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
