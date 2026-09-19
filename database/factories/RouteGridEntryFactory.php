<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\RouteGridEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RouteGridEntry> */
class RouteGridEntryFactory extends Factory
{
    protected $model = RouteGridEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'origin_city_id' => City::factory(),
            'destination_city_id' => City::factory(),
            'company_name' => $this->faker->randomElement(['UTB', 'STC', 'Bonoua Trans']),
            'price' => $this->faker->numberBetween(4, 20) * 500,
            'distance_km' => $this->faker->numberBetween(80, 600),
            'duration_minutes' => $this->faker->numberBetween(90, 600),
            'departure_times' => ['06:00', '12:00', '18:00'],
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
