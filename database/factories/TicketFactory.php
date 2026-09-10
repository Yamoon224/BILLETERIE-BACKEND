<?php

namespace Database\Factories;

use App\Domains\Shared\Support\Reference;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Ticket> */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'trip_id' => fn (array $attributes) => Booking::find($attributes['booking_id'])?->trip_id,
            'code' => Reference::make('BIL'),
            'seat_number' => '1A',
            'passenger_name' => $this->faker->name(),
            'passenger_phone' => '+225'.$this->faker->numerify('0#########'),
            'status' => TicketStatus::Issued,
            // Signature factice : les tests qui verifient reellement la
            // signature passent par TicketIssuanceService, qui la calcule.
            // La poser ici evite d'avoir a resoudre le signeur pour creer un
            // billet dont la signature n'est pas le sujet du test.
            'signature' => str_repeat('0', 32),
            'key_version' => 1,
        ];
    }

    public function onSeat(string $seatNumber): static
    {
        return $this->state(fn () => ['seat_number' => $seatNumber]);
    }

    public function scanned(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Used,
            'scanned_at' => Carbon::now(),
        ]);
    }

    /** Billet annule : sa place est retournee a la vente. */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Cancelled,
            'released_seat_number' => $attributes['seat_number'] ?? null,
            'seat_number' => null,
        ]);
    }
}
