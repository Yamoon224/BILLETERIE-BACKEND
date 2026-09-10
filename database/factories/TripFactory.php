<?php

namespace Database\Factories;

use App\Domains\Scheduling\Enums\TripStatus;
use App\Domains\Shared\Support\Reference;
use App\Models\Itinerary;
use App\Models\Station;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Trip> */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $departsAt = Carbon::now()->addDay()->setTime(7, 0);

        return [
            'reference' => Reference::dated('DEP', $departsAt),
            'itinerary_id' => Itinerary::factory(),
            /*
             * La compagnie est deduite de l'itineraire, et la cle est donc
             * declaree **apres** lui : une fabrique resout ses attributs dans
             * l'ordre de declaration, et une fermeture placee avant recevrait
             * l'objet fabrique plutot que l'identifiant resolu.
             */
            'company_id' => fn (array $attributes) => Itinerary::find($attributes['itinerary_id'])?->company_id,
            'vehicle_id' => Vehicle::factory(),
            'departure_station_id' => Station::factory(),
            'arrival_station_id' => Station::factory(),
            'departs_at' => $departsAt,
            'arrives_at' => $departsAt->copy()->addHours(4),
            'price' => 7000,
            // Copies figees du vehicule, comme le fait TripService.
            'seat_capacity' => 70,
            'seats_per_row' => 4,
            'status' => TripStatus::Scheduled,
        ];
    }

    /** Depart cale sur un vehicule existant, capacite et plan recopies. */
    public function onVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn () => [
            'vehicle_id' => $vehicle->id,
            'company_id' => $vehicle->company_id,
            'seat_capacity' => $vehicle->seat_capacity,
            'seats_per_row' => $vehicle->seats_per_row,
        ]);
    }

    public function departingAt(Carbon $departsAt): static
    {
        return $this->state(fn () => [
            'reference' => Reference::dated('DEP', $departsAt),
            'departs_at' => $departsAt,
            'arrives_at' => $departsAt->copy()->addHours(4),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => TripStatus::Cancelled,
            'cancellation_reason' => 'Vehicule immobilise.',
            'cancelled_at' => Carbon::now(),
        ]);
    }

    public function boarding(): static
    {
        return $this->state(fn () => ['status' => TripStatus::Boarding]);
    }
}
