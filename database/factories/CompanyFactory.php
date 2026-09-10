<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = 'Transport '.$this->faker->unique()->lastName();

        return [
            'code' => strtoupper($this->faker->unique()->bothify('??##')),
            'name' => $name,
            'legal_name' => $name.' SARL',
            'phone' => '+225'.$this->faker->numerify('0#########'),
            'email' => $this->faker->unique()->companyEmail(),
            // Nul par defaut : la compagnie suit le bareme de la plateforme.
            'commission_per_mille' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
