<?php

namespace Database\Factories;

use App\Domains\Housing\Enums\ApartmentAmenity;
use App\Models\Apartment;
use App\Models\City;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Apartment> */
class ApartmentFactory extends Factory
{
    protected $model = Apartment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'city_id' => City::factory(),
            'title' => 'Appartement meuble a '.$this->faker->streetName(),
            'description' => $this->faker->paragraph(),
            'neighborhood' => $this->faker->randomElement(['Assinie Plage', 'Grand-Bassam France', 'Jacqueville Centre', 'Riviera', 'Cocody']),
            'address_line' => $this->faker->streetAddress(),
            'bedrooms' => $this->faker->numberBetween(1, 4),
            'bathrooms' => $this->faker->numberBetween(1, 3),
            'capacity' => $this->faker->numberBetween(2, 8),
            'price_per_night' => $this->faker->numberBetween(15, 150) * 1000,
            'amenities' => $this->faker->randomElements(ApartmentAmenity::values(), $this->faker->numberBetween(2, 5)),
            'cover_photo_url' => null,
            'photo_urls' => [],
            'is_featured' => false,
            'is_active' => true,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
