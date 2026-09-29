<?php

namespace Database\Factories;

use App\Domains\Promotions\Enums\PromotionKind;
use App\Domains\Promotions\Enums\PromotionZone;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Promotion> */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6),
            'subtitle' => $this->faker->sentence(8),
            'zone' => PromotionZone::FeaturedTile,
            'kind' => PromotionKind::Editorial,
            'advertiser_name' => null,
            'is_active' => true,
        ];
    }

    public function heroBanner(): static
    {
        return $this->state(fn () => ['zone' => PromotionZone::HeroBanner]);
    }

    public function advertisement(): static
    {
        return $this->state(fn () => [
            'kind' => PromotionKind::Advertisement,
            'advertiser_name' => $this->faker->company(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
