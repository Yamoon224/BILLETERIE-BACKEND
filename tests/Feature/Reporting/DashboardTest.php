<?php

namespace Tests\Feature\Reporting;

use App\Domains\Booking\DTOs\BookingDraft;
use App\Domains\Booking\DTOs\PassengerDraft;
use App\Domains\Booking\Enums\BookingChannel;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Payments\Enums\MobileMoneyProvider;
use App\Domains\Payments\Services\PaymentService;
use App\Models\Company;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Critere de recette du cahier des charges :
 *
 *   « Le tableau de bord distingue correctement les encaissements par mode de
 *     paiement. »
 *
 * Le second point verifie ici est le cloisonnement entre compagnies : c'est la
 * regle de securite la plus sensible de la plateforme, et la plus facile a
 * casser par inadvertance en ajoutant un filtre lu dans la requete.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Company $utb;

    private Company $stc;

    private Trip $utbTrip;

    private Trip $stcTrip;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->utb, $this->utbTrip] = $this->makeCompanyWithTrip();
        [$this->stc, $this->stcTrip] = $this->makeCompanyWithTrip();
    }

    /** @return array{0: Company, 1: Trip} */
    private function makeCompanyWithTrip(): array
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->withCapacity(60)->create(['company_id' => $company->id]);
        $itinerary = Itinerary::factory()->create(['company_id' => $company->id]);

        $trip = Trip::factory()
            ->onVehicle($vehicle)
            ->departingAt(Carbon::now()->addDay())
            ->create(['itinerary_id' => $itinerary->id, 'company_id' => $company->id, 'price' => 7000]);

        return [$company, $trip];
    }

    private function sellOnline(Trip $trip, string $seat): void
    {
        $booking = app(BookingService::class)->reserve(new BookingDraft(
            tripId: $trip->id,
            channel: BookingChannel::Online,
            customerName: 'Client '.$seat,
            customerPhone: '+22507'.str_pad((string) crc32($trip->id.$seat), 8, '0', STR_PAD_LEFT),
            customerEmail: null,
            passengers: [new PassengerDraft($seat, 'Client '.$seat)],
        ));

        app(PaymentService::class)->payWithMobileMoney(
            $booking,
            MobileMoneyProvider::OrangeMoney,
            '+2250700000001',
        );
    }

    private function sellAtCounter(Trip $trip, string $seat, User $agent): void
    {
        $booking = app(BookingService::class)->reserve(new BookingDraft(
            tripId: $trip->id,
            channel: BookingChannel::Counter,
            customerName: 'Guichet '.$seat,
            customerPhone: '+22505'.str_pad((string) crc32($trip->id.$seat.'c'), 8, '0', STR_PAD_LEFT),
            customerEmail: null,
            passengers: [new PassengerDraft($seat, 'Guichet '.$seat)],
            soldByUserId: $agent->id,
        ));

        app(PaymentService::class)->collectCash($booking, $agent->id);
    }

    // -------------------------------------------------------------------------

    #[Test]
    public function le_tableau_de_bord_ventile_les_encaissements_par_moyen_de_paiement(): void
    {
        $manager = $this->userWithRole('company_manager', $this->utb);
        $agent = $this->userWithRole('agent', $this->utb);

        $this->sellOnline($this->utbTrip, '1A');
        $this->sellOnline($this->utbTrip, '1B');
        $this->sellAtCounter($this->utbTrip, '1C', $agent);

        $response = $this->actingAs($manager)->getJson('/api/dashboard')->assertOk();

        $byMethod = array_column($response->json('data.revenue_by_payment_method'), null, 'method');

        $this->assertSame(2, $byMethod['mobile_money']['count']);
        $this->assertSame(14000, $byMethod['mobile_money']['amount']);
        $this->assertSame(1, $byMethod['cash']['count']);
        $this->assertSame(7000, $byMethod['cash']['amount']);

        // Les moyens sans encaissement restent presents a zero : une ligne
        // absente laisserait croire a un filtre oublie.
        $this->assertSame(0, $byMethod['card']['count']);
    }

    #[Test]
    public function le_tableau_de_bord_agrege_les_ventes_et_la_commission(): void
    {
        $manager = $this->userWithRole('company_manager', $this->utb);

        $this->sellOnline($this->utbTrip, '2A');
        $this->sellOnline($this->utbTrip, '2B');

        $summary = $this->actingAs($manager)->getJson('/api/dashboard')->json('data.summary');

        $this->assertSame(2, $summary['bookings']);
        $this->assertSame(2, $summary['tickets']);
        $this->assertSame(14000, $summary['gross_revenue']);
        // 2,5 % de 7000 = 175, arrondi au plus proche, par reservation.
        $this->assertSame(350, $summary['commission']);
        $this->assertSame(13650, $summary['net_revenue']);
        $this->assertSame(7000, $summary['average_basket']);
    }

    #[Test]
    public function le_taux_de_remplissage_est_calcule_par_depart(): void
    {
        $manager = $this->userWithRole('company_manager', $this->utb);

        foreach (['3A', '3B', '3C'] as $seat) {
            $this->sellOnline($this->utbTrip, $seat);
        }

        $occupancy = array_column($this->actingAs($manager)->getJson('/api/dashboard')->json('data.occupancy'), null, 'trip_id')[$this->utbTrip->id];

        $this->assertSame(60, $occupancy['capacity']);
        $this->assertSame(3, $occupancy['sold']);
        $this->assertSame(0.05, $occupancy['occupancy_rate']);
    }

    /** La frontiere de securite : une compagnie ne voit pas la recette d'une autre. */
    #[Test]
    public function un_gestionnaire_ne_voit_que_la_recette_de_sa_compagnie(): void
    {
        $utbManager = $this->userWithRole('company_manager', $this->utb);

        $this->sellOnline($this->utbTrip, '4A');
        $this->sellOnline($this->stcTrip, '4A');
        $this->sellOnline($this->stcTrip, '4B');

        $summary = $this->actingAs($utbManager)->getJson('/api/dashboard')->json('data.summary');

        $this->assertSame(1, $summary['bookings']);
        $this->assertSame(7000, $summary['gross_revenue']);
    }

    /**
     * Le perimetre vient du jeton : un identifiant de compagnie passe dans la
     * requete ne doit rien changer.
     */
    #[Test]
    public function un_identifiant_de_compagnie_passe_en_parametre_est_ignore(): void
    {
        $utbManager = $this->userWithRole('company_manager', $this->utb);

        $this->sellOnline($this->utbTrip, '5A');
        $this->sellOnline($this->stcTrip, '5A');

        $summary = $this->actingAs($utbManager)
            ->getJson('/api/dashboard?company_id='.$this->stc->id)
            ->json('data.summary');

        $this->assertSame(1, $summary['bookings']);
    }

    #[Test]
    public function l_administrateur_plateforme_voit_toutes_les_compagnies(): void
    {
        $admin = $this->userWithRole('platform_admin');

        $this->sellOnline($this->utbTrip, '6A');
        $this->sellOnline($this->stcTrip, '6A');

        $summary = $this->actingAs($admin)->getJson('/api/dashboard')->json('data.summary');

        $this->assertSame(2, $summary['bookings']);
        $this->assertSame(14000, $summary['gross_revenue']);
    }

    #[Test]
    public function un_agent_n_a_pas_acces_au_tableau_de_bord(): void
    {
        $agent = $this->userWithRole('agent', $this->utb);

        $this->actingAs($agent)->getJson('/api/dashboard')->assertStatus(403);
    }

    #[Test]
    public function l_export_des_ventes_est_servi_en_csv(): void
    {
        $manager = $this->userWithRole('company_manager', $this->utb);

        $this->sellOnline($this->utbTrip, '7A');

        $response = $this->actingAs($manager)->get('/api/exports/bookings');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        // BOM UTF-8 en tete : sans elle, Excel sous Windows lit le fichier en
        // ANSI et casse les accents des noms de villes.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        // Separateur point-virgule : celui qu'attend un tableur francophone.
        $this->assertStringContainsString('Reference;"Date de vente";Canal', $csv);
        $this->assertStringContainsString(';7000;175;6825;', $csv);
    }
}
