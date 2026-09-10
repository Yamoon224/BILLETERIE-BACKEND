<?php

namespace Database\Factories;

use App\Domains\Payments\Enums\PaymentMethod;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Shared\Support\Reference;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => Reference::make('PAY'),
            'booking_id' => Booking::factory(),
            'method' => PaymentMethod::Cash,
            'gateway' => 'counter',
            'amount' => 7000,
            'currency' => (string) config('ticketing.currency'),
            'status' => PaymentStatus::Succeeded,
            'authorized_at' => Carbon::now(),
            'paid_at' => Carbon::now(),
        ];
    }

    public function pendingMobileMoney(string $externalReference): static
    {
        return $this->state(fn () => [
            'method' => PaymentMethod::MobileMoney,
            'gateway' => 'simulated',
            'status' => PaymentStatus::Pending,
            'external_reference' => $externalReference,
            'authorized_at' => null,
            'paid_at' => null,
        ]);
    }
}
