<?php

namespace Database\Factories;

use App\Domains\Booking\Enums\BookingChannel;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Shared\Support\Money;
use App\Domains\Shared\Support\Reference;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $total = 7000;
        $perMille = (int) config('ticketing.commission_per_mille');

        return [
            'reference' => Reference::make('RES'),
            'trip_id' => Trip::factory(),
            'company_id' => fn (array $attributes) => Trip::find($attributes['trip_id'])?->company_id,
            'channel' => BookingChannel::Online,
            'status' => BookingStatus::Confirmed,
            'customer_name' => $this->faker->name(),
            'customer_phone' => '+225'.$this->faker->numerify('0#########'),
            'customer_email' => $this->faker->safeEmail(),
            'seats_count' => 1,
            'total_amount' => $total,
            'currency' => (string) config('ticketing.currency'),
            'commission_amount' => Money::commission($total, $perMille),
            'commission_per_mille' => $perMille,
            'confirmed_at' => Carbon::now(),
        ];
    }

    /** Reservation en attente de paiement, avec son echeance de blocage. */
    public function pending(?Carbon $expiresAt = null): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Pending,
            'confirmed_at' => null,
            'expires_at' => $expiresAt ?? Carbon::now()->addMinutes((int) config('ticketing.hold_minutes')),
        ]);
    }

    /** Blocage deja perime : sert aux tests de la commande d'expiration. */
    public function expiredHold(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Pending,
            'confirmed_at' => null,
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);
    }

    public function counter(): static
    {
        return $this->state(fn () => ['channel' => BookingChannel::Counter]);
    }
}
