<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Station> */
class StationFactory extends Factory
{
    protected $model = Station::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            // Gare partagee par defaut : c'est le cas le plus courant des
            // gares routieres, et celui qui exerce le plus de code.
            'company_id' => null,
            'name' => 'Gare '.$this->faker->streetName(),
            'address' => $this->faker->streetAddress(),
            'phone' => '+225'.$this->faker->numerify('0#########'),
            'is_active' => true,
        ];
    }
}
