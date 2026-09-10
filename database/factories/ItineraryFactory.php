<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Company;
use App\Models\Itinerary;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Itinerary> */
class ItineraryFactory extends Factory
{
    protected $model = Itinerary::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'origin_city_id' => City::factory(),
            'destination_city_id' => City::factory(),
            'distance_km' => $this->faker->numberBetween(80, 600),
            'duration_minutes' => $this->faker->numberBetween(90, 600),
            // Tarifs realistes du marche ivoirien, arrondis au multiple de 500.
            'base_price' => $this->faker->numberBetween(6, 30) * 500,
            'is_active' => true,
        ];
    }
}
