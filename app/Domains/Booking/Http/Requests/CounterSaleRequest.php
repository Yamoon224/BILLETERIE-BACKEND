<?php

namespace App\Domains\Booking\Http\Requests;

use App\Domains\Booking\DTOs\BookingDraft;
use App\Domains\Booking\DTOs\PassengerDraft;
use App\Domains\Booking\Enums\BookingChannel;
use App\Domains\Payments\Enums\MobileMoneyProvider;
use App\Domains\Payments\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vente au guichet par un agent.
 *
 * Deux differences avec la reservation en ligne. Le moyen d'encaissement est
 * exige — l'agent constate un paiement, il ne le declenche pas —, et une
 * reference client optionnelle rend la vente idempotente : la tablette
 * l'attribue avant l'envoi, si bien qu'une reponse perdue ne cree pas une
 * seconde vente au moment ou elle est rejouee.
 */
class CounterSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'trip_id' => ['required', 'uuid', 'exists:trips,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'station_id' => ['nullable', 'uuid', 'exists:stations,id'],
            'notes' => ['nullable', 'string', 'max:500'],

            // Le guichet vend des groupes plus grands qu'en ligne : une famille
            // entiere se presente au comptoir avec ses bagages.
            'passengers' => ['required', 'array', 'min:1', 'max:20'],
            'passengers.*.seat_number' => ['required', 'string', 'max:6'],
            'passengers.*.name' => ['required', 'string', 'max:255'],
            'passengers.*.phone' => ['nullable', 'string', 'max:20'],

            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'payment_provider' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('payment_method') === PaymentMethod::MobileMoney->value),
                Rule::in(MobileMoneyProvider::values()),
            ],
            'payer_msisdn' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('payment_method') === PaymentMethod::MobileMoney->value),
                'string', 'max:20',
            ],

            'client_reference' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function draft(?string $agentUserId): BookingDraft
    {
        /** @var list<array{seat_number: string, name: string, phone?: string|null}> $passengers */
        $passengers = $this->input('passengers');

        return new BookingDraft(
            tripId: (string) $this->input('trip_id'),
            channel: BookingChannel::Counter,
            customerName: (string) $this->input('customer_name'),
            customerPhone: (string) $this->input('customer_phone'),
            customerEmail: $this->input('customer_email'),
            passengers: array_map(PassengerDraft::fromArray(...), $passengers),
            soldByUserId: $agentUserId,
            stationId: $this->input('station_id'),
            clientReference: $this->input('client_reference'),
            notes: $this->input('notes'),
        );
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from((string) $this->input('payment_method'));
    }

    public function paymentProvider(): ?MobileMoneyProvider
    {
        $provider = $this->input('payment_provider');

        return $provider !== null ? MobileMoneyProvider::from((string) $provider) : null;
    }
}
