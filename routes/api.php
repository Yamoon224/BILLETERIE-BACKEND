<?php

use App\Domains\Audit\Http\Controllers\AuditLogController;
use App\Domains\Auth\Http\Controllers\AuthController;
use App\Domains\Booking\Http\Controllers\BookingController;
use App\Domains\Booking\Http\Controllers\CounterSaleController;
use App\Domains\Booking\Http\Controllers\OfflineSyncController;
use App\Domains\CarRental\Http\Controllers\RentalVehicleController;
use App\Domains\Favorites\Http\Controllers\FavoriteController;
use App\Domains\Housing\Http\Controllers\ApartmentController;
use App\Domains\Network\Http\Controllers\CityController;
use App\Domains\Network\Http\Controllers\CompanyController;
use App\Domains\Network\Http\Controllers\ItineraryController;
use App\Domains\Network\Http\Controllers\StationController;
use App\Domains\Network\Http\Controllers\VehicleController;
use App\Domains\Partners\Http\Controllers\PartnerController;
use App\Domains\Payments\Http\Controllers\PaymentController;
use App\Domains\Payments\Http\Controllers\PaymentWebhookController;
use App\Domains\Reporting\Http\Controllers\DashboardController;
use App\Domains\Reporting\Http\Controllers\SalesExportController;
use App\Domains\Scheduling\Http\Controllers\TripController;
use App\Domains\Scheduling\Http\Controllers\TripSearchController;
use App\Domains\Shared\Http\Controllers\HealthController;
use App\Domains\Ticketing\Http\Controllers\TicketController;
use App\Domains\Ticketing\Http\Controllers\TicketValidationController;
use App\Domains\Users\Http\Controllers\ProfileController;
use App\Domains\Users\Http\Controllers\RoleController;
use App\Domains\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST
|--------------------------------------------------------------------------
|
| Conventions : ressources au pluriel, verbes HTTP porteurs de l'intention,
| 201 sur creation, 204 sur suppression sans corps, 422 sur validation, 409 sur
| conflit d'etat metier. Les actions qui ne sont pas un CRUD (scanner un
| billet, annuler un depart, synchroniser un lot) sont exposees comme des
| sous-ressources POST plutot que comme des verbes inventes.
|
| Trois zones se distinguent :
|
|   1. **Public** — le parcours voyageur jusqu'au paiement. Exiger un compte
|      pour consulter des horaires ferait perdre la moitie des visiteurs, et le
|      cahier des charges demande une reservation de bout en bout sans
|      intervention manuelle. La securite tient a l'imprevisibilite des
|      references (voir App\Domains\Shared\Support\Reference), pas a un jeton.
|
|   2. **Agent** — le guichet et le controle a l'embarquement. Jeton Sanctum,
|      permissions granulaires, et des limites de debit calibrees pour un
|      terminal qui rejoue ses envois apres une coupure reseau.
|
|   3. **Exploitation** — programmation, suivi, administration. Cloisonne par
|      compagnie via App\Domains\Shared\Support\CompanyScope, resolu depuis le
|      jeton et jamais depuis la requete.
|
*/

Route::get('/health', HealthController::class);

// =============================================================================
// Zone publique — parcours voyageur
// =============================================================================

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');

// Referentiel de recherche : villes desservies et departs disponibles.
Route::get('/cities/options', [CityController::class, 'options']);
Route::get('/trips/search', TripSearchController::class);

// Plan de salle : consulte a l'ecran de choix de place, avant tout compte.
Route::get('/trips/{trip}/seat-map', [TripController::class, 'seatMap']);

/*
 * Recherche publique des deux autres piliers (appartements, location auto).
 *
 * Meme logique que /trips/search : parcourable sans compte, uniquement les
 * fiches actives. Aucune reservation en ligne ne s'y adosse encore — voir
 * README — la recherche ne fait donc que lister et filtrer.
 */
Route::get('/apartments/search', [ApartmentController::class, 'search']);
Route::get('/rental-vehicles/search', [RentalVehicleController::class, 'search']);

/*
 * Reservation et paiement sans compte.
 *
 * Limitees en debit : ces routes creent des lignes en base et declenchent des
 * appels a un agregateur payant. Vingt reservations par minute et par adresse
 * couvrent largement un usage humain, y compris une agence qui reserve pour
 * plusieurs clients depuis la meme connexion.
 */
Route::middleware('throttle:20,1')->group(function (): void {
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{booking}/payments', [PaymentController::class, 'initiate']);
});

// Retrouver son billet avec la reference recue par SMS.
Route::get('/bookings/reference/{reference}', [BookingController::class, 'showByReference']);
Route::get('/tickets/{code}/qr', [TicketController::class, 'qrCode']);

/*
 * Rappel de l'agregateur de paiement.
 *
 * Non authentifie au sens applicatif : l'agregateur n'a pas de compte, sa
 * signature *est* son authentification (voir PaymentWebhookController). Le
 * debit est large parce qu'un prestataire rejoue ses rappels en rafale apres
 * une indisponibilite de notre cote — les brider ferait perdre des
 * confirmations de paiement deja debitees chez le voyageur.
 */
Route::post('/payments/webhook', PaymentWebhookController::class)->middleware('throttle:120,1');

// =============================================================================
// Zone authentifiee
// =============================================================================

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // --- Compte de l'appelant ------------------------------------------------
    // Aucune permission : ces routes n'agissent que sur lui-meme, et la
    // garantie tient a cela, pas a un controle d'identifiant.
    Route::patch('/me', [ProfileController::class, 'update']);
    Route::put('/me/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1');
    Route::delete('/me/tokens', [ProfileController::class, 'revokeOtherTokens']);
    Route::get('/me/bookings', [BookingController::class, 'mine']);

    // --- Trajets favoris -------------------------------------------------------
    // Aucune permission dediee : un voyageur ne gere que ses propres favoris,
    // comme pour ses reservations ci-dessus.
    Route::get('/me/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{favorite}', [FavoriteController::class, 'destroy']);

    // --- Reservations --------------------------------------------------------
    Route::middleware('permission:bookings.view')->group(function (): void {
        Route::get('/bookings', [BookingController::class, 'index']);
    });

    // Un voyageur consulte et annule ses propres reservations sans permission
    // dediee : le controleur verifie qu'il en est le titulaire.
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

    // --- Guichet -------------------------------------------------------------
    Route::middleware('permission:sales.create')->group(function (): void {
        Route::post('/counter-sales', CounterSaleController::class);

        /*
         * Synchronisation des ventes hors ligne.
         *
         * Debit volontairement plus large que la vente unitaire : une tablette
         * qui retrouve le reseau apres une matinee de coupure envoie plusieurs
         * lots coup sur coup, et les brider retarderait la reconciliation de
         * ventes deja encaissees.
         */
        Route::post('/offline-sales/sync', OfflineSyncController::class)->middleware('throttle:60,1');
    });

    // --- Controle a l'embarquement -------------------------------------------
    Route::middleware('permission:tickets.validate')->group(function (): void {
        /*
         * Le scan n'est volontairement pas limite en debit au meme niveau que
         * le reste : a la porte d'un bus de 70 places, l'agent scanne plusieurs
         * billets par seconde pendant plusieurs minutes, et un 429 en plein
         * embarquement arreterait la file.
         */
        Route::post('/tickets/validate', TicketValidationController::class)->middleware('throttle:600,1');
        Route::get('/trips/{trip}/manifest', [TicketController::class, 'forTrip']);
    });

    Route::middleware('permission:tickets.view')->group(function (): void {
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/{ticket}/print', [TicketController::class, 'printableQrCode']);
    });

    // --- Encaissements --------------------------------------------------------
    Route::middleware('permission:payments.view')->group(function (): void {
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    });

    Route::middleware('permission:payments.refund')->group(function (): void {
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);
    });

    // --- Programmation des departs --------------------------------------------
    Route::middleware('permission:trips.view')->group(function (): void {
        Route::get('/trips', [TripController::class, 'index']);
        Route::get('/trips/{trip}', [TripController::class, 'show']);
    });

    Route::middleware('permission:trips.manage')->group(function (): void {
        Route::post('/trips', [TripController::class, 'store']);
        Route::patch('/trips/{trip}', [TripController::class, 'update']);
        Route::post('/trips/{trip}/cancel', [TripController::class, 'cancel']);
        Route::post('/trips/{trip}/status', [TripController::class, 'changeStatus']);
        Route::delete('/trips/{trip}', [TripController::class, 'destroy']);
    });

    // --- Referentiel reseau -----------------------------------------------------
    Route::middleware('permission:network.view')->group(function (): void {
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::get('/companies/{company}', [CompanyController::class, 'show']);
        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/cities/{city}', [CityController::class, 'show']);
        Route::get('/stations', [StationController::class, 'index']);
        Route::get('/stations/{station}', [StationController::class, 'show']);
        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show']);
        Route::get('/itineraries', [ItineraryController::class, 'index']);
        Route::get('/itineraries/{itinerary}', [ItineraryController::class, 'show']);
    });

    Route::middleware('permission:network.manage')->group(function (): void {
        Route::patch('/companies/{company}', [CompanyController::class, 'update']);

        Route::post('/stations', [StationController::class, 'store']);
        Route::patch('/stations/{station}', [StationController::class, 'update']);
        Route::delete('/stations/{station}', [StationController::class, 'destroy']);

        Route::post('/vehicles', [VehicleController::class, 'store']);
        Route::patch('/vehicles/{vehicle}', [VehicleController::class, 'update']);
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);

        Route::post('/itineraries', [ItineraryController::class, 'store']);
        Route::patch('/itineraries/{itinerary}', [ItineraryController::class, 'update']);
        Route::delete('/itineraries/{itinerary}', [ItineraryController::class, 'destroy']);
    });

    /*
     * Creation et suppression de compagnies, et referentiel des villes :
     * administrateur plateforme uniquement. Une compagnie ne cree pas ses
     * concurrentes, et ne renomme pas une ville que toutes les autres
     * utilisent.
     */
    Route::middleware('permission:platform.manage')->group(function (): void {
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::delete('/companies/{company}', [CompanyController::class, 'destroy']);

        Route::post('/cities', [CityController::class, 'store']);
        Route::patch('/cities/{city}', [CityController::class, 'update']);
        Route::delete('/cities/{city}', [CityController::class, 'destroy']);

        // Onboarding d'un partenaire : comme une compagnie, il ne s'inscrit
        // pas lui-meme.
        Route::post('/partners', [PartnerController::class, 'store']);
        Route::delete('/partners/{partner}', [PartnerController::class, 'destroy']);
    });

    // --- Partenaires : appartements, location auto ----------------------------
    Route::middleware('permission:partners.view')->group(function (): void {
        Route::get('/partners', [PartnerController::class, 'index']);
        Route::get('/partners/{partner}', [PartnerController::class, 'show']);
    });

    Route::middleware('permission:partners.manage')->group(function (): void {
        Route::patch('/partners/{partner}', [PartnerController::class, 'update']);
    });

    Route::middleware('permission:housing.view')->group(function (): void {
        Route::get('/apartments', [ApartmentController::class, 'index']);
        Route::get('/apartments/{apartment}', [ApartmentController::class, 'show']);
    });

    Route::middleware('permission:housing.manage')->group(function (): void {
        Route::post('/apartments', [ApartmentController::class, 'store']);
        Route::patch('/apartments/{apartment}', [ApartmentController::class, 'update']);
        Route::delete('/apartments/{apartment}', [ApartmentController::class, 'destroy']);
    });

    Route::middleware('permission:car_rental.view')->group(function (): void {
        Route::get('/rental-vehicles', [RentalVehicleController::class, 'index']);
        Route::get('/rental-vehicles/{rentalVehicle}', [RentalVehicleController::class, 'show']);
    });

    Route::middleware('permission:car_rental.manage')->group(function (): void {
        Route::post('/rental-vehicles', [RentalVehicleController::class, 'store']);
        Route::patch('/rental-vehicles/{rentalVehicle}', [RentalVehicleController::class, 'update']);
        Route::delete('/rental-vehicles/{rentalVehicle}', [RentalVehicleController::class, 'destroy']);
    });

    // --- Suivi d'activite --------------------------------------------------------
    Route::middleware('permission:reports.view')->group(function (): void {
        Route::get('/dashboard', DashboardController::class);
        Route::get('/exports/bookings', [SalesExportController::class, 'bookings']);
        Route::get('/exports/occupancy', [SalesExportController::class, 'occupancy']);
    });

    // --- Comptes ------------------------------------------------------------------
    Route::middleware('permission:users.view')->group(function (): void {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::get('/roles', RoleController::class);
    });

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::post('/users', [UserController::class, 'store']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    // --- Journal d'audit ------------------------------------------------------------
    // Lecture seule : un journal qu'on peut modifier depuis l'application ne
    // prouve plus rien. Aucune route d'ecriture n'existe, par construction.
    Route::middleware('permission:audit.view')->group(function (): void {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/facets', [AuditLogController::class, 'facets']);
    });
});
