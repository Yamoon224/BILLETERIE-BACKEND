<?php

namespace App\Domains\Booking\Http\Requests;

use App\Domains\Booking\DTOs\BookingDraft;
use App\Domains\Booking\DTOs\PassengerDraft;
use App\Domains\Booking\Enums\BookingChannel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reservation en ligne par un voyageur.
 *
 * Ni le prix, ni la compagnie, ni le canal ne figurent dans les regles : ils se
 * deduisent du depart. Les accepter en entree laisserait un client fixer son
 * propre tarif.
 */
class StoreBookingRequest extends FormRequest
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
            // Le telephone est obligatoire meme pour un compte connecte :
            // c'est par lui qu'arrive le billet, et le numero du compte peut
            // ne pas etre celui du voyageur du jour.
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],

            // Dix places par reservation : au-dela, c'est un affretement, qui
            // se traite au guichet et non en ligne.
            'passengers' => ['required', 'array', 'min:1', 'max:10'],
            'passengers.*.seat_number' => ['required', 'string', 'max:6'],
            'passengers.*.name' => ['required', 'string', 'max:255'],
            'passengers.*.phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function draft(?string $customerUserId): BookingDraft
    {
        /** @var list<array{seat_number: string, name: string, phone?: string|null}> $passengers */
        $passengers = $this->input('passengers');

        return new BookingDraft(
            tripId: (string) $this->input('trip_id'),
            channel: BookingChannel::Online,
            customerName: (string) $this->input('customer_name'),
            customerPhone: (string) $this->input('customer_phone'),
            customerEmail: $this->input('customer_email'),
            passengers: array_map(PassengerDraft::fromArray(...), $passengers),
            customerUserId: $customerUserId,
        );
    }
}
