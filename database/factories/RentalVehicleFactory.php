<?php

namespace Database\Factories;

use App\Domains\CarRental\Enums\FuelType;
use App\Domains\CarRental\Enums\RentalVehicleCategory;
use App\Domains\CarRental\Enums\TransmissionType;
use App\Models\City;
use App\Models\Partner;
use App\Models\RentalVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentalVehicle> */
class RentalVehicleFactory extends Factory
{
    protected $model = RentalVehicle::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'city_id' => City::factory(),
            'brand' => $this->faker->randomElement(['Toyota', 'Hyundai', 'Kia', 'Renault', 'Volkswagen']),
            'model' => $this->faker->randomElement(['Corolla', 'Tucson', 'Sportage', 'Duster', 'Golf']),
            'year' => $this->faker->numberBetween(2018, 2026),
            'category' => RentalVehicleCategory::Citadine,
            'transmission' => TransmissionType::Manual,
            'fuel_type' => FuelType::Petrol,
            'seats' => 5,
            'price_per_day' => $this->faker->numberBetween(20, 80) * 1000,
            'with_driver_available' => $this->faker->boolean(),
            'plate_number' => strtoupper($this->faker->unique()->bothify('####??-##')),
            'cover_photo_url' => null,
            'photo_urls' => [],
            'is_featured' => false,
            'is_active' => true,
        ];
    }

    public function suv(): static
    {
        return $this->state(fn () => [
            'category' => RentalVehicleCategory::Suv,
            'seats' => 7,
            'price_per_day' => $this->faker->numberBetween(45, 120) * 1000,
        ]);
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
