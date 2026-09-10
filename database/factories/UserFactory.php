<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** Mot de passe hache une seule fois pour toute la suite de tests. */
    private static ?string $password = null;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '+225'.$this->faker->unique()->numerify('0#########'),
            'email_verified_at' => now(),
            // Hacher a chaque utilisateur cree couterait, sur une suite qui en
            // cree des centaines, plusieurs secondes de bcrypt pour rien.
            'password' => self::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** Compte rattache a une compagnie : agent ou gestionnaire. */
    public function forCompany(string $companyId): static
    {
        return $this->state(fn () => ['company_id' => $companyId]);
    }
}
