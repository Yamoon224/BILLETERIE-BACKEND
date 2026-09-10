<?php

namespace Database\Factories;

use App\Domains\Network\Enums\VehicleClass;
use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'registration' => strtoupper($this->faker->unique()->bothify('####??-##')),
            'model' => $this->faker->randomElement(['Toyota Coaster', 'Higer KLQ6128', 'Mercedes Sprinter', 'Yutong ZK6122']),
            'class' => VehicleClass::Standard,
            // Un car interurbain courant : 70 places en 2+2.
            'seat_capacity' => 70,
            'seats_per_row' => 4,
            'is_active' => true,
        ];
    }

    public function withCapacity(int $capacity, int $seatsPerRow = 4): static
    {
        return $this->state(fn () => [
            'seat_capacity' => $capacity,
            'seats_per_row' => $seatsPerRow,
        ]);
    }

    public function minibus(): static
    {
        return $this->state(fn () => [
            'class' => VehicleClass::Minibus,
            'seat_capacity' => 18,
            'seats_per_row' => 3,
        ]);
    }
}
