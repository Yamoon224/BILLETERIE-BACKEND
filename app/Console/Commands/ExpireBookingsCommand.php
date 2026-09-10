<?php

namespace App\Console\Commands;

use App\Domains\Booking\Services\BookingExpiryService;
use Illuminate\Console\Command;

/**
 * Libere les places des reservations non payees dont le delai est ecoule.
 *
 * Planifiee toutes les minutes (voir routes/console.php). La frequence n'est
 * pas gratuite : chaque minute d'attente est une minute pendant laquelle un bus
 * s'affiche complet alors qu'il ne l'est pas, et la veille d'une fete cela se
 * traduit par des voyageurs restes a quai devant des sieges vides.
 */
class ExpireBookingsCommand extends Command
{
    protected $signature = 'bookings:expire {--limit=200 : Nombre maximal de reservations traitees par execution}';

    protected $description = 'Libere les places des reservations en attente dont le delai de paiement est ecoule.';

    public function handle(BookingExpiryService $service): int
    {
        $result = $service->releaseExpiredHolds(limit: (int) $this->option('limit'));

        $this->info(sprintf(
            '%d reservation(s) expiree(s), %d place(s) remise(s) en vente, %d echec(s).',
            $result['expired'],
            $result['seats_released'],
            $result['failed'],
        ));

        // Un echec isole ne fait pas echouer la commande : le planificateur la
        // relancera dans une minute, et les reservations restantes ont ete
        // traitees. Signaler un echec global ferait sonner une alerte pour une
        // situation qui se resout d'elle-meme.
        return self::SUCCESS;
    }
}
