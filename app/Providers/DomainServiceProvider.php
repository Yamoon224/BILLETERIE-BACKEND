<?php

namespace App\Providers;

use App\Domains\Audit\Contracts\AuditLogRepositoryContract;
use App\Domains\Audit\Repositories\EloquentAuditLogRepository;
use App\Domains\Booking\Contracts\BookingRepositoryContract;
use App\Domains\Booking\Repositories\EloquentBookingRepository;
use App\Domains\CarRental\Contracts\RentalVehicleRepositoryContract;
use App\Domains\CarRental\Repositories\EloquentRentalVehicleRepository;
use App\Domains\Favorites\Contracts\FavoriteRepositoryContract;
use App\Domains\Favorites\Repositories\EloquentFavoriteRepository;
use App\Domains\Housing\Contracts\ApartmentRepositoryContract;
use App\Domains\Housing\Repositories\EloquentApartmentRepository;
use App\Domains\Network\Contracts\CityRepositoryContract;
use App\Domains\Network\Contracts\CompanyRepositoryContract;
use App\Domains\Network\Contracts\ItineraryRepositoryContract;
use App\Domains\Network\Contracts\StationRepositoryContract;
use App\Domains\Network\Contracts\VehicleRepositoryContract;
use App\Domains\Network\Repositories\EloquentCityRepository;
use App\Domains\Network\Repositories\EloquentCompanyRepository;
use App\Domains\Network\Repositories\EloquentItineraryRepository;
use App\Domains\Network\Repositories\EloquentStationRepository;
use App\Domains\Network\Repositories\EloquentVehicleRepository;
use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Domains\Partners\Contracts\PartnerRepositoryContract;
use App\Domains\Partners\Repositories\EloquentPartnerRepository;
use App\Domains\Payments\Contracts\PaymentGatewayContract;
use App\Domains\Payments\Contracts\PaymentRepositoryContract;
use App\Domains\Payments\Repositories\EloquentPaymentRepository;
use App\Domains\Reporting\Contracts\SalesReportReaderContract;
use App\Domains\Reporting\Repositories\EloquentSalesReportReader;
use App\Domains\RouteGrid\Contracts\RouteGridRepositoryContract;
use App\Domains\RouteGrid\Repositories\EloquentRouteGridRepository;
use App\Domains\Scheduling\Contracts\TripLookupContract;
use App\Domains\Scheduling\Contracts\TripRepositoryContract;
use App\Domains\Scheduling\Repositories\EloquentTripRepository;
use App\Domains\Ticketing\Contracts\OccupiedSeatReaderContract;
use App\Domains\Ticketing\Contracts\QrCodeRendererContract;
use App\Domains\Ticketing\Contracts\TicketRepositoryContract;
use App\Domains\Ticketing\Contracts\TicketSignerContract;
use App\Domains\Ticketing\Repositories\EloquentOccupiedSeatReader;
use App\Domains\Ticketing\Repositories\EloquentTicketRepository;
use App\Domains\Ticketing\Support\EndroidQrCodeRenderer;
use App\Domains\Ticketing\Support\HmacTicketSigner;
use App\Domains\Users\Contracts\UserRepositoryContract;
use App\Domains\Users\Repositories\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Point unique de cablage entre contrats et implementations (inversion des
 * dependances).
 *
 * Aucun service metier ne reference une classe concrete de persistance ni de
 * prestataire : tout passe par les interfaces listees ici. C'est ce qui permet
 * de substituer une implementation en test, ou de changer d'agregateur de
 * paiement, en touchant ce seul fichier.
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * Contrats de persistance et leurs implementations Eloquent.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        // --- Referentiel reseau ---------------------------------------------
        CompanyRepositoryContract::class => EloquentCompanyRepository::class,
        CityRepositoryContract::class => EloquentCityRepository::class,
        StationRepositoryContract::class => EloquentStationRepository::class,
        VehicleRepositoryContract::class => EloquentVehicleRepository::class,
        ItineraryRepositoryContract::class => EloquentItineraryRepository::class,

        // --- Parcours voyageur -------------------------------------------------
        FavoriteRepositoryContract::class => EloquentFavoriteRepository::class,
        RouteGridRepositoryContract::class => EloquentRouteGridRepository::class,

        // --- Partenaires : appartements, location auto -----------------------
        PartnerRepositoryContract::class => EloquentPartnerRepository::class,
        ApartmentRepositoryContract::class => EloquentApartmentRepository::class,
        RentalVehicleRepositoryContract::class => EloquentRentalVehicleRepository::class,

        // --- Exploitation ----------------------------------------------------
        TripRepositoryContract::class => EloquentTripRepository::class,
        BookingRepositoryContract::class => EloquentBookingRepository::class,
        TicketRepositoryContract::class => EloquentTicketRepository::class,
        PaymentRepositoryContract::class => EloquentPaymentRepository::class,

        // --- Lectures etroites (segregation d'interface) ----------------------
        //
        // `TripLookupContract` n'expose a la vente que la lecture d'un depart :
        // le domaine Booking ne peut donc ni reprogrammer ni annuler un depart,
        // et cela se lit dans la signature de ses services.
        //
        // `OccupiedSeatReaderContract` n'expose a la recherche et a la vente
        // que le decompte des places prises : ni la recherche ni le tableau de
        // bord ne recoivent de quoi emettre ou annuler un billet.
        TripLookupContract::class => EloquentTripRepository::class,
        OccupiedSeatReaderContract::class => EloquentOccupiedSeatReader::class,
        SalesReportReaderContract::class => EloquentSalesReportReader::class,

        // --- Comptes et audit -------------------------------------------------
        UserRepositoryContract::class => EloquentUserRepository::class,
        AuditLogRepositoryContract::class => EloquentAuditLogRepository::class,

        // --- Billetterie ------------------------------------------------------
        TicketSignerContract::class => HmacTicketSigner::class,
        QrCodeRendererContract::class => EndroidQrCodeRenderer::class,
    ];

    public function register(): void
    {
        $this->registerPaymentGateway();
        $this->registerNotificationSender();
    }

    /**
     * Agregateur de paiement, choisi par configuration.
     *
     * Un pilote inconnu leve immediatement plutot que de retomber
     * silencieusement sur la simulation : une plateforme qui croit encaisser
     * alors qu'elle simule est le pire scenario imaginable, et il ne se
     * decouvrirait qu'au premier rapprochement de caisse.
     */
    private function registerPaymentGateway(): void
    {
        $this->app->singleton(PaymentGatewayContract::class, function (): PaymentGatewayContract {
            $name = (string) config('payments.gateway');
            $driver = config("payments.gateways.{$name}.driver");

            if (! is_string($driver) || ! class_exists($driver)) {
                throw new RuntimeException(
                    "Agregateur de paiement « {$name} » inconnu. Verifiez PAYMENT_GATEWAY et config/payments.php.",
                );
            }

            /** @var PaymentGatewayContract $gateway */
            $gateway = $this->app->make($driver);

            return $gateway;
        });
    }

    /**
     * Pilote d'envoi des notifications.
     *
     * Enregistre en singleton : le pilote de test conserve les messages en
     * memoire, et une seconde instance ferait inspecter au test un envoi qui
     * n'a pas eu lieu.
     */
    private function registerNotificationSender(): void
    {
        $this->app->singleton(NotificationSenderContract::class, function (): NotificationSenderContract {
            $name = (string) config('notifications.driver');
            $driver = config("notifications.drivers.{$name}");

            if (! is_string($driver) || ! class_exists($driver)) {
                // Ici le repli est acceptable, et meme souhaitable : une
                // notification perdue ne compromet ni une vente ni une recette,
                // alors qu'une exception au demarrage rendrait l'API muette.
                return $this->app->make(ArrayNotificationSender::class);
            }

            /** @var NotificationSenderContract $sender */
            $sender = $this->app->make($driver);

            return $sender;
        });
    }
}
