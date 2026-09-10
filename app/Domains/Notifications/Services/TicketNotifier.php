<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Shared\Support\Money;
use App\Models\Booking;
use App\Models\NotificationDispatch;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Envoi du billet electronique au voyageur.
 *
 * Deux principes gouvernent ce service.
 *
 * **L'envoi ne peut pas faire echouer la vente.** Il est appele apres la
 * confirmation, hors de sa transaction, et toute exception y est capturee.
 * Un operateur SMS indisponible ne doit pas transformer un paiement acquis en
 * erreur 500 — le voyageur a paye, son billet existe, il est consultable en
 * ligne et reimprimable au guichet.
 *
 * **Chaque tentative est tracee, surtout quand elle echoue.** « Je n'ai pas
 * recu mon billet » est la premiere reclamation d'un support de billetterie ;
 * sans journal, la reponse serait une conjecture.
 *
 * Le message tient volontairement en un SMS : sur un forfait prepaye, un
 * message qui se scinde en trois coute trois fois, et les segments arrivent
 * parfois dans le desordre.
 */
final class TicketNotifier
{
    public function __construct(private readonly NotificationSenderContract $sender) {}

    /**
     * Notifie le voyageur de sa reservation confirmee.
     *
     * @return list<NotificationDispatch>
     */
    public function sendBookingConfirmation(Booking $booking): array
    {
        $booking->loadMissing(['trip.itinerary.originCity', 'trip.itinerary.destinationCity', 'tickets']);

        $dispatches = [];
        $channels = [NotificationChannel::Sms];

        foreach ($channels as $channel) {
            $dispatches[] = $this->dispatch($booking, $channel, $this->confirmationBody($booking));
        }

        return $dispatches;
    }

    private function dispatch(Booking $booking, NotificationChannel $channel, string $body): NotificationDispatch
    {
        $dispatch = NotificationDispatch::create([
            'booking_id' => $booking->id,
            'channel' => $channel,
            'recipient' => $booking->customer_phone,
            'template' => 'booking_confirmed',
            // Les variables, pas le texte rendu : un modele corrige se rejoue
            // alors sur les envois passes.
            'payload' => [
                'booking_reference' => $booking->reference,
                'seats' => $booking->tickets->pluck('seat_number')->filter()->values()->all(),
            ],
            'status' => NotificationStatus::Pending,
            'attempts' => 0,
        ]);

        try {
            $accepted = $this->sender->send(new NotificationMessage(
                channel: $channel,
                recipient: $booking->customer_phone,
                body: $body,
            ));

            $dispatch->update([
                'status' => $accepted ? NotificationStatus::Sent : NotificationStatus::Failed,
                'attempts' => 1,
                'sent_at' => $accepted ? Carbon::now() : null,
                'error' => $accepted ? null : 'Message refuse par l operateur.',
            ]);
        } catch (Throwable $exception) {
            // Capture volontaire et large : aucune defaillance d'operateur ne
            // doit remonter jusqu'a la reponse HTTP d'un paiement reussi.
            $dispatch->update([
                'status' => NotificationStatus::Failed,
                'attempts' => 1,
                'error' => mb_substr($exception->getMessage(), 0, 250),
            ]);
        }

        return $dispatch;
    }

    private function confirmationBody(Booking $booking): string
    {
        $trip = $booking->trip;
        $seats = $booking->tickets->pluck('seat_number')->filter()->implode(', ');

        return sprintf(
            '%s > %s le %s a %s. Reservation %s, %d place(s)%s. Montant %s. Presentez ce code a l embarquement.',
            $trip->itinerary->originCity->name,
            $trip->itinerary->destinationCity->name,
            $trip->departs_at->format('d/m'),
            $trip->departs_at->format('H\hi'),
            $booking->reference,
            $booking->seats_count,
            $seats !== '' ? " ({$seats})" : '',
            Money::format($booking->total_amount, $booking->currency),
        );
    }
}
