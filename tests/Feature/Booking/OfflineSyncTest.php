<?php

namespace Tests\Feature\Booking;

use App\Domains\Booking\Enums\BookingChannel;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Itinerary;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Critere de recette du cahier des charges :
 *
 *   « Un agent peut vendre au guichet et fonctionner sans connexion internet,
 *     avec synchronisation correcte au retour du reseau. »
 *
 * « Correcte » se decompose en trois proprietes, une par groupe de tests :
 * rien n'est perdu, rien n'est double, et rien n'est avale en silence.
 */
class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private User $agent;

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

        $this->agent = $this->userWithRole('agent', $company);
    }

    /** @return array<string, mixed> */
    private function sale(string $clientReference, string $seat, ?Carbon $soldAt = null): array
    {
        return [
            'client_reference' => $clientReference,
            'sold_at' => ($soldAt ?? Carbon::now()->subMinutes(30))->toIso8601String(),
            'trip_id' => $this->trip->id,
            'customer_name' => 'Client '.$seat,
            'customer_phone' => '+22505'.str_pad((string) crc32($seat), 8, '0', STR_PAD_LEFT),
            'payment_method' => 'cash',
            'passengers' => [['seat_number' => $seat, 'name' => 'Client '.$seat]],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sales
     * @return TestResponse<Response>
     */
    private function sync(array $sales): TestResponse
    {
        return $this->actingAs($this->agent)->postJson('/api/offline-sales/sync', ['sales' => $sales]);
    }

    // --- Rien n'est perdu -----------------------------------------------------

    #[Test]
    public function un_lot_de_ventes_hors_ligne_est_enregistre(): void
    {
        $response = $this->sync([
            $this->sale('TAB01-0001', '1A'),
            $this->sale('TAB01-0002', '1B'),
            $this->sale('TAB01-0003', '1C'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.summary.accepted', 3)
            ->assertJsonPath('data.summary.rejected', 0);

        $this->assertSame(3, Booking::count());
        $this->assertSame(3, Ticket::count());
        // Les especes encaissees au guichet doivent apparaitre en recette.
        $this->assertSame(3, Payment::where('status', 'succeeded')->count());
    }

    /**
     * L'heure reelle de la vente est conservee, distincte de celle de la
     * synchronisation : sans elle, une vente de 23 h 50 remontee a 00 h 10
     * tomberait dans la caisse du lendemain.
     */
    #[Test]
    public function l_heure_reelle_de_la_vente_est_conservee(): void
    {
        $soldAt = Carbon::now()->subHours(6)->startOfMinute();

        $this->sync([$this->sale('TAB01-0010', '2A', $soldAt)])->assertOk();

        $booking = Booking::firstOrFail();

        $this->assertSame(BookingChannel::OfflineCounter, $booking->channel);
        $this->assertSame($soldAt->toDateTimeString(), $booking->sold_offline_at?->toDateTimeString());
        $this->assertNotNull($booking->synced_at);
        $this->assertSame($this->agent->id, $booking->sold_by_user_id);
    }

    // --- Rien n'est double -----------------------------------------------------

    /**
     * Le cas qui justifie tout le mecanisme : la reponse du serveur se perd,
     * la tablette rejoue son envoi. Sans idempotence, la meme place serait
     * vendue deux fois au meme voyageur.
     */
    #[Test]
    public function rejouer_le_meme_lot_ne_cree_aucune_vente_supplementaire(): void
    {
        $sales = [$this->sale('TAB01-0100', '3A'), $this->sale('TAB01-0101', '3B')];

        $first = $this->sync($sales)->assertOk();
        $second = $this->sync($sales)->assertOk();

        $second->assertJsonPath('data.summary.accepted', 0)
            ->assertJsonPath('data.summary.duplicate', 2);

        $this->assertSame(2, Booking::count());
        $this->assertSame(2, Payment::count());

        // La vente rendue au second passage est bien la premiere.
        $this->assertSame(
            $first->json('data.results.0.booking_reference'),
            $second->json('data.results.0.booking_reference'),
        );
    }

    // --- Rien n'est avale ------------------------------------------------------

    /**
     * Un lot n'est pas atomique : une vente en conflit ne doit pas faire
     * perdre les autres, qui ont bien eu lieu dans le monde reel.
     */
    #[Test]
    public function un_conflit_isole_ne_fait_pas_echouer_le_reste_du_lot(): void
    {
        // La place 4A est vendue en ligne pendant que la tablette est hors reseau.
        $this->postJson('/api/bookings', [
            'trip_id' => $this->trip->id,
            'customer_name' => 'Client en ligne',
            'customer_phone' => '+2250700000099',
            'passengers' => [['seat_number' => '4A', 'name' => 'Client en ligne']],
        ])->assertCreated();

        $response = $this->sync([
            $this->sale('TAB01-0200', '4A'),
            $this->sale('TAB01-0201', '4B'),
            $this->sale('TAB01-0202', '4C'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.accepted', 2)
            ->assertJsonPath('data.summary.rejected', 1)
            // Le motif revient a la tablette : « place deja vendue » appelle un
            // remboursement, pas la meme suite qu'un depart annule.
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.error_code', 'seat_unavailable')
            ->assertJsonPath('data.results.1.status', 'accepted')
            ->assertJsonPath('data.results.2.status', 'accepted');
    }

    /**
     * Une horloge de tablette dereglee — pile de sauvegarde morte, cas courant
     * sur du materiel bon marche — ne doit pas faire tomber une vente dans la
     * recette de demain.
     */
    #[Test]
    public function une_vente_datee_dans_le_futur_est_refusee(): void
    {
        $this->sync([$this->sale('TAB01-0300', '5A', Carbon::now()->addHours(3))])
            ->assertOk()
            ->assertJsonPath('data.summary.rejected', 1)
            ->assertJsonPath('data.results.0.error_code', 'offline_sale_in_future');

        $this->assertSame(0, Booking::count());
    }

    #[Test]
    public function une_vente_trop_ancienne_est_refusee_pour_arbitrage(): void
    {
        $tooOld = Carbon::now()->subHours((int) config('ticketing.offline_sync_max_age_hours') + 1);

        $this->sync([$this->sale('TAB01-0400', '6A', $tooOld)])
            ->assertOk()
            ->assertJsonPath('data.summary.rejected', 1)
            ->assertJsonPath('data.results.0.error_code', 'offline_sale_too_old');
    }

    #[Test]
    public function la_reference_client_est_obligatoire(): void
    {
        $sale = $this->sale('IGNORED', '7A');
        unset($sale['client_reference']);

        $this->sync([$sale])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    #[Test]
    public function la_synchronisation_exige_la_permission_de_vente(): void
    {
        $passenger = $this->userWithRole('passenger');

        $this->actingAs($passenger)
            ->postJson('/api/offline-sales/sync', ['sales' => [$this->sale('TAB01-0500', '8A')]])
            ->assertStatus(403);
    }
}
