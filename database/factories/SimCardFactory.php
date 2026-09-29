<?php

namespace Database\Factories;

use App\Domains\Sms\Enums\SimOperator;
use App\Models\SimCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SimCard> */
class SimCardFactory extends Factory
{
    protected $model = SimCard::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'operator' => $this->faker->randomElement(SimOperator::values()),
            'phone_number' => '+225'.$this->faker->numerify('0#########'),
            'balance' => $this->faker->numberBetween(1000, 10000),
            'low_balance_threshold' => 1000,
            'is_active' => true,
        ];
    }

    public function lowBalance(): static
    {
        return $this->state(fn () => ['balance' => 800]);
    }
}
