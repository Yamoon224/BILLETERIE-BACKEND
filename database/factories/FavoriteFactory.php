<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Favorite> */
class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'origin_city_id' => City::factory(),
            'destination_city_id' => City::factory(),
        ];
    }
}
