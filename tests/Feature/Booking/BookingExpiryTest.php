<?php

namespace Tests\Feature\Booking;

use App\Domains\Booking\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Itinerary;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Liberation des places dont le blocage a expire.
 *
 * Sans ce mecanisme, un bus s'afficherait complet a cause de reservations
 * jamais payees — et la veille d'une fete, cela se compte en voyageurs restes
 * a quai devant des sieges vides.
 */
class BookingExpiryTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->withCapacity(60)->create(['company_id' => $company->id]);
        $itinerary = Itinerary::factory()->create(['company_id' => $company->id]);

        $this->trip = Trip::factory()
            ->onVehicle($vehicle)
            ->departingAt(Carbon::now()->addDay())
            ->create(['itinerary_id' => $itinerary->id, 'company_id' => $company->id, 'price' => 7000]);
    }

    private function reserve(string $seat, string $phone): string
    {
        return $this->postJson('/api/bookings', [
            'trip_id' => $this->trip->id,
            'customer_name' => 'Client '.$seat,
            'customer_phone' => $phone,
            'passengers' => [['seat_number' => $seat, 'name' => 'Client '.$seat]],
        ])->assertCreated()->json('data.id');
    }

    // -------------------------------------------------------------------------

    #[Test]
    public function une_reservation_impayee_expire_et_libere_ses_places(): void
    {
        $bookingId = $this->reserve('1A', '+2250700000020');

        Carbon::setTestNow(Carbon::now()->addMinutes((int) config('ticketing.hold_minutes') + 1));

        $this->artisan('bookings:expire')->assertSuccessful();

        $booking = Booking::findOrFail($bookingId);

        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertSame('Delai de paiement ecoule.', $booking->cancellation_reason);

        // La place est rendue a la vente : `seat_number` vide, mais l'ancienne
        // valeur conservee pour que l'historique reste explicable.
        $ticket = Ticket::where('booking_id', $bookingId)->firstOrFail();
        $this->assertNull($ticket->seat_number);
        $this->assertSame('1A', $ticket->released_seat_number);

        Carbon::setTestNow();
    }

    #[Test]
    public function la_place_liberee_est_immediatement_revendable(): void
    {
        $this->reserve('2A', '+2250700000021');

        Carbon::setTestNow(Carbon::now()->addMinutes((int) config('ticketing.hold_minutes') + 1));

        $this->artisan('bookings:expire')->assertSuccessful();

        $this->reserve('2A', '+2250700000022');

        $this->assertSame(1, Ticket::where('seat_number', '2A')->count());

        Carbon::setTestNow();
    }

    #[Test]
    public function une_reservation_dont_le_delai_court_encore_n_est_pas_touchee(): void
    {
        $bookingId = $this->reserve('3A', '+2250700000023');

        // Une minute avant l'echeance.
        Carbon::setTestNow(Carbon::now()->addMinutes((int) config('ticketing.hold_minutes') - 1));

        $this->artisan('bookings:expire')->assertSuccessful();

        $this->assertSame(BookingStatus::Pending, Booking::findOrFail($bookingId)->status);

        Carbon::setTestNow();
    }

    /**
     * Une reservation payee n'expire jamais : la confirmation efface son
     * echeance, ce qui la met hors de portee de la commande meme si celle-ci
     * etait mal filtree.
     */
    #[Test]
    public function une_reservation_payee_n_expire_pas(): void
    {
        $bookingId = $this->reserve('4A', '+2250700000024');

        $this->postJson("/api/bookings/{$bookingId}/payments", [
            'provider' => 'orange_money',
            'payer_msisdn' => '+2250700000024',
        ])->assertCreated();

        Carbon::setTestNow(Carbon::now()->addHours(3));

        $this->artisan('bookings:expire')->assertSuccessful();

        $booking = Booking::findOrFail($bookingId);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->expires_at);
        $this->assertNotNull(Ticket::where('booking_id', $bookingId)->firstOrFail()->seat_number);

        Carbon::setTestNow();
    }

    /**
     * L'expiration n'est pas une annulation : elle ne genere ni remboursement
     * ni ligne de recette negative, puisque rien n'a jamais ete encaisse. Les
     * confondre fausserait la recette autant que le taux de remplissage.
     */
    #[Test]
    public function l_expiration_se_distingue_de_l_annulation(): void
    {
        $expired = $this->reserve('5A', '+2250700000025');

        Carbon::setTestNow(Carbon::now()->addMinutes((int) config('ticketing.hold_minutes') + 1));
        $this->artisan('bookings:expire')->assertSuccessful();
        Carbon::setTestNow();

        $this->assertSame(BookingStatus::Expired, Booking::findOrFail($expired)->status);
        $this->assertSame(0, Booking::where('status', BookingStatus::Cancelled)->count());
    }
}
