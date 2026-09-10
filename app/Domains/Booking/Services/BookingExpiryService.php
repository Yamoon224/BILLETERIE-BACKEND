<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Contracts\BookingRepositoryContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Liberation des places dont le blocage a expire.
 *
 * Appele par une commande planifiee (`bookings:expire`). Le traitement se fait
 * reservation par reservation, et une defaillance isolee ne fait pas tomber le
 * lot : une reservation qu'on n'arrive pas a expirer immobilise quelques
 * places, une commande qui s'arrete a la premiere erreur les immobilise
 * toutes — sur un bus la veille d'une fete, la difference se compte en
 * voyageurs restes a quai.
 */
final class BookingExpiryService
{
    public function __construct(
        private readonly BookingRepositoryContract $bookings,
        private readonly BookingService $service,
    ) {}

    /**
     * @return array{expired: int, seats_released: int, failed: int}
     */
    public function releaseExpiredHolds(?Carbon $now = null, int $limit = 200): array
    {
        $now ??= Carbon::now();

        $expired = 0;
        $seatsReleased = 0;
        $failed = 0;

        foreach ($this->bookings->expiredHolds($now, $limit) as $booking) {
            try {
                $seats = $booking->seats_count;
                $this->service->expire($booking, $now);
                $expired++;
                $seatsReleased += $seats;
            } catch (Throwable $exception) {
                $failed++;
                Log::warning('Expiration de reservation impossible', [
                    'booking_reference' => $booking->reference,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return ['expired' => $expired, 'seats_released' => $seatsReleased, 'failed' => $failed];
    }
}
