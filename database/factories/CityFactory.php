<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<City> */
class CityFactory extends Factory
{
    protected $model = City::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = $this->faker->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'region' => $this->faker->randomElement(['Lagunes', 'Vallee du Bandama', 'Savanes', 'Bas-Sassandra']),
            'is_active' => true,
        ];
    }

    public function named(string $name, string $slug): static
    {
        return $this->state(fn () => ['name' => $name, 'slug' => $slug]);
    }
}
