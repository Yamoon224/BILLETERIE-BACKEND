<?php

namespace App\Domains\Ticketing\Services;

use App\Domains\Shared\Support\Reference;
use App\Domains\Ticketing\Contracts\QrCodeRendererContract;
use App\Domains\Ticketing\Contracts\TicketRepositoryContract;
use App\Domains\Ticketing\Contracts\TicketSignerContract;
use App\Domains\Ticketing\DTOs\TicketQrPayload;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\Ticket;
use App\Models\Trip;

/**
 * Emission des billets d'une reservation.
 *
 * Appele **a l'interieur** de la transaction de vente, jamais apres : c'est
 * l'insertion des billets qui heurte l'index unique `(trip_id, seat_number)`
 * et fait echouer une double vente. Emettre les billets dans un second temps
 * reviendrait a confirmer une reservation avant de savoir si ses places sont
 * disponibles.
 */
final class TicketIssuanceService
{
    public function __construct(
        private readonly TicketRepositoryContract $tickets,
        private readonly TicketSignerContract $signer,
        private readonly QrCodeRendererContract $qrCodes,
    ) {}

    /**
     * @param  list<array{seat_number: string, passenger_name: string, passenger_phone?: string|null}>  $passengers
     * @return list<Ticket>
     */
    public function issueForBooking(Booking $booking, Trip $trip, array $passengers): array
    {
        $issued = [];

        foreach ($passengers as $passenger) {
            $issued[] = $this->issueOne($booking, $trip, $passenger);
        }

        return $issued;
    }

    /**
     * @param  array{seat_number: string, passenger_name: string, passenger_phone?: string|null}  $passenger
     */
    private function issueOne(Booking $booking, Trip $trip, array $passenger): Ticket
    {
        $code = Reference::make('BIL', (int) config('ticketing.reference_random_length', 6));
        $seat = strtoupper($passenger['seat_number']);

        // La signature porte sur le contenu final du billet, place comprise :
        // modifier la place d'un billet emis invaliderait sa signature, ce qui
        // est exactement le comportement voulu.
        $payload = new TicketQrPayload(
            ticketCode: $code,
            tripReference: $trip->reference,
            seatNumber: $seat,
            departsAtTimestamp: $trip->departs_at->getTimestamp(),
        );

        return $this->tickets->create([
            'booking_id' => $booking->id,
            'trip_id' => $trip->id,
            'code' => $code,
            'seat_number' => $seat,
            'passenger_name' => $passenger['passenger_name'],
            'passenger_phone' => $passenger['passenger_phone'] ?? $booking->customer_phone,
            'status' => TicketStatus::Issued,
            'signature' => $this->signer->sign($payload),
            'key_version' => $this->signer->currentKeyVersion(),
        ]);
    }

    /**
     * Contenu a encoder dans le QR code d'un billet deja emis.
     *
     * Reconstruit depuis la base plutot que conserve : la signature stockee et
     * les champs signes vivent deja en base, et dupliquer la chaine complete
     * ferait exister deux verites pour un meme billet.
     */
    public function qrContent(Ticket $ticket): string
    {
        $trip = $ticket->trip;

        $payload = new TicketQrPayload(
            ticketCode: $ticket->code,
            tripReference: $trip->reference,
            seatNumber: $ticket->displaySeatNumber() ?? '',
            departsAtTimestamp: $trip->departs_at->getTimestamp(),
        );

        return $payload->encode($ticket->signature, $ticket->key_version);
    }

    public function qrSvg(Ticket $ticket, int $size = 320): string
    {
        return $this->qrCodes->toSvg($this->qrContent($ticket), $size);
    }

    /** QR matriciel pour l'imprimante thermique Bluetooth du guichet. */
    public function qrPng(Ticket $ticket, int $size = 384): string
    {
        return $this->qrCodes->toPng($this->qrContent($ticket), $size);
    }

    /**
     * Annule un billet et rend sa place a la vente.
     *
     * Un billet deja scanne n'est pas annulable : le voyageur est monte, la
     * place est consommee, et la « liberer » la remettrait en vente pour un bus
     * qui roule.
     */
    public function cancel(Ticket $ticket, TicketStatus $status = TicketStatus::Cancelled): Ticket
    {
        if ($ticket->isScanned()) {
            return $ticket;
        }

        return $this->tickets->releaseSeat($ticket, $status);
    }
}
