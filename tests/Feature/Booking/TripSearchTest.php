<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\City;
use App\Models\Company;
use App\Models\Itinerary;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Recherche de departs : le premier ecran du parcours voyageur, public.
 */
class TripSearchTest extends TestCase
{
    use RefreshDatabase;

    private Itinerary $itinerary;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $abidjan = City::factory()->named('Abidjan', 'abidjan')->create();
        $bouake = City::factory()->named('Bouake', 'bouake')->create();

        $this->vehicle = Vehicle::factory()->withCapacity(8)->create(['company_id' => $company->id]);
        $this->itinerary = Itinerary::factory()->create([
            'company_id' => $company->id,
            'origin_city_id' => $abidjan->id,
            'destination_city_id' => $bouake->id,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function trip(Carbon $departsAt, array $attributes = []): Trip
    {
        return Trip::factory()
            ->onVehicle($this->vehicle)
            ->departingAt($departsAt)
            ->create(['itinerary_id' => $this->itinerary->id, ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return TestResponse<Response>
     */
    private function search(array $query = []): TestResponse
    {
        return $this->getJson('/api/trips/search?'.http_build_query([
            'origin' => 'abidjan',
            'destination' => 'bouake',
            'date' => Carbon::tomorrow()->toDateString(),
            ...$query,
        ]));
    }

    #[Test]
    public function la_recherche_est_publique_et_trie_par_heure_de_depart(): void
    {
        $late = $this->trip(Carbon::tomorrow()->setTime(18, 0));
        $early = $this->trip(Carbon::tomorrow()->setTime(6, 0));

        $response = $this->search()->assertOk();

        $this->assertSame([$early->id, $late->id], array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function la_recherche_expose_les_places_disponibles(): void
    {
        $trip = $this->trip(Carbon::tomorrow()->setTime(8, 0));
        $booking = Booking::factory()->create(['trip_id' => $trip->id]);
        Ticket::factory()->onSeat('1A')->create(['booking_id' => $booking->id, 'trip_id' => $trip->id]);
        Ticket::factory()->onSeat('1B')->create(['booking_id' => $booking->id, 'trip_id' => $trip->id]);

        $this->search()
            ->assertJsonPath('data.0.seat_capacity', 8)
            ->assertJsonPath('data.0.seats_taken', 2)
            ->assertJsonPath('data.0.seats_available', 6);
    }

    /** Un billet annule a rendu sa place : il ne doit plus compter comme occupe. */
    #[Test]
    public function une_place_liberee_n_est_pas_comptee_comme_prise(): void
    {
        $trip = $this->trip(Carbon::tomorrow()->setTime(8, 0));
        $booking = Booking::factory()->create(['trip_id' => $trip->id]);
        Ticket::factory()->onSeat('2A')->cancelled()->create(['booking_id' => $booking->id, 'trip_id' => $trip->id]);

        $this->search()->assertJsonPath('data.0.seats_available', 8);
    }

    #[Test]
    public function les_departs_annules_n_apparaissent_pas(): void
    {
        $this->trip(Carbon::tomorrow()->setTime(9, 0))->update(['status' => 'cancelled']);

        $this->search()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function le_filtre_de_places_disponibles_masque_les_departs_trop_pleins(): void
    {
        $trip = $this->trip(Carbon::tomorrow()->setTime(10, 0));
        $booking = Booking::factory()->create(['trip_id' => $trip->id]);

        foreach (['1A', '1B', '1C', '1D', '2A', '2B', '2C'] as $seat) {
            Ticket::factory()->onSeat($seat)->create(['booking_id' => $booking->id, 'trip_id' => $trip->id]);
        }

        // Une place restante : un groupe de deux ne doit pas voir ce depart.
        $this->search(['passengers' => 2, 'only_available' => 1])->assertJsonCount(0, 'data');
        $this->search(['passengers' => 1, 'only_available' => 1])->assertJsonCount(1, 'data');
    }

    #[Test]
    public function le_plan_de_salle_marque_les_places_prises(): void
    {
        $trip = $this->trip(Carbon::tomorrow()->setTime(11, 0));
        $booking = Booking::factory()->create(['trip_id' => $trip->id]);
        Ticket::factory()->onSeat('2C')->create(['booking_id' => $booking->id, 'trip_id' => $trip->id]);

        $response = $this->getJson("/api/trips/{$trip->id}/seat-map")->assertOk();

        $response->assertJsonPath('data.capacity', 8)
            ->assertJsonPath('data.taken', 1)
            ->assertJsonPath('data.rows.1.seats.2.number', '2C')
            ->assertJsonPath('data.rows.1.seats.2.is_taken', true)
            ->assertJsonPath('data.rows.0.aisle_after', 2);
    }

    #[Test]
    public function la_ville_d_arrivee_doit_differer_du_depart(): void
    {
        $this->search(['destination' => 'abidjan'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('destination');
    }
}
