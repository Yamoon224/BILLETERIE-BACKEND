<?php

namespace Database\Factories;

use App\Domains\Partners\Enums\PartnerType;
use App\Models\City;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Partner> */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'type' => PartnerType::Both,
            'phone' => '+225'.$this->faker->unique()->numerify('0#########'),
            'whatsapp' => null,
            'email' => $this->faker->unique()->companyEmail(),
            'city_id' => City::factory(),
            'logo_path' => null,
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function housing(): static
    {
        return $this->state(fn () => ['type' => PartnerType::Housing]);
    }

    public function carRental(): static
    {
        return $this->state(fn () => ['type' => PartnerType::CarRental]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
