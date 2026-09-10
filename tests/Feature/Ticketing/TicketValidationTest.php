<?php

namespace Tests\Feature\Ticketing;

use App\Domains\Booking\DTOs\BookingDraft;
use App\Domains\Booking\DTOs\PassengerDraft;
use App\Domains\Booking\Enums\BookingChannel;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Scheduling\Enums\TripStatus;
use App\Domains\Ticketing\Enums\ScanOutcome;
use App\Domains\Ticketing\Enums\TicketStatus;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Itinerary;
use App\Models\Ticket;
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
 *   « Un billet scanne une premiere fois est refuse lors d'une seconde
 *     tentative de scan. »
 *
 * C'est la garantie la plus visible du systeme — celle qui se constate devant
 * la porte d'un bus — et ces tests l'exercent par l'API, dans les conditions
 * reelles du controle.
 */
class TicketValidationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Trip $trip;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $vehicle = Vehicle::factory()->withCapacity(20)->create(['company_id' => $this->company->id]);
        $itinerary = Itinerary::factory()->create(['company_id' => $this->company->id]);

        // Depart dans une heure : dans la fenetre d'embarquement, qui ouvre
        // deux heures avant.
        $this->trip = Trip::factory()
            ->onVehicle($vehicle)
            ->departingAt(Carbon::now()->addHour())
            ->create(['itinerary_id' => $itinerary->id, 'company_id' => $this->company->id]);

        $this->agent = $this->userWithRole('agent', $this->company);
    }

    private function issueTicket(string $seat = '1A'): Ticket
    {
        $booking = app(BookingService::class)->reserve(new BookingDraft(
            tripId: $this->trip->id,
            channel: BookingChannel::Counter,
            customerName: 'Awa Kone',
            customerPhone: '+2250700000009',
            customerEmail: null,
            passengers: [new PassengerDraft($seat, 'Awa Kone')],
        ));

        return $booking->tickets()->firstOrFail();
    }

    private function qrOf(Ticket $ticket): string
    {
        return app(TicketIssuanceService::class)->qrContent($ticket->load('trip'));
    }

    // -------------------------------------------------------------------------

    #[Test]
    public function un_billet_valide_autorise_l_embarquement(): void
    {
        $ticket = $this->issueTicket();

        $response = $this->actingAs($this->agent)->postJson('/api/tickets/validate', [
            'code' => $this->qrOf($ticket),
            'trip_id' => $this->trip->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.outcome', ScanOutcome::Accepted->value)
            ->assertJsonPath('data.ticket.seat_number', '1A');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Used, $ticket->status);
        $this->assertNotNull($ticket->scanned_at);
        $this->assertSame($this->agent->id, $ticket->scanned_by_user_id);
    }

    /** LE critere de recette. */
    #[Test]
    public function un_second_scan_du_meme_billet_est_refuse(): void
    {
        $ticket = $this->issueTicket();
        $qr = $this->qrOf($ticket);

        $this->actingAs($this->agent)
            ->postJson('/api/tickets/validate', ['code' => $qr, 'trip_id' => $this->trip->id])
            ->assertOk();

        $second = $this->actingAs($this->agent)
            ->postJson('/api/tickets/validate', ['code' => $qr, 'trip_id' => $this->trip->id]);

        $second->assertStatus(409)
            ->assertJsonPath('data.accepted', false)
            ->assertJsonPath('data.outcome', ScanOutcome::AlreadyUsed->value)
            ->assertJsonPath('data.is_suspicious', true);

        // L'heure du premier passage est rendue : c'est elle qui clot la
        // discussion devant la porte du bus.
        $this->assertNotNull($second->json('data.previously_scanned_at'));
    }

    /**
     * Meme garantie par la saisie manuelle du code : c'est le chemin de repli
     * quand le QR est illisible, et il ne doit pas ouvrir une seconde entree.
     */
    #[Test]
    public function un_second_scan_est_refuse_meme_par_saisie_manuelle_du_code(): void
    {
        $ticket = $this->issueTicket();

        $this->actingAs($this->agent)
            ->postJson('/api/tickets/validate', ['code' => $this->qrOf($ticket)])
            ->assertOk();

        $this->actingAs($this->agent)
            ->postJson('/api/tickets/validate', ['code' => strtolower($ticket->code)])
            ->assertStatus(409)
            ->assertJsonPath('data.outcome', ScanOutcome::AlreadyUsed->value);
    }

    /**
     * Le rejeu hors ligne n'est pas une fraude : la tablette renvoie ses scans
     * au retour du reseau, parfois plusieurs fois. Le meme geste, reconnu a sa
     * reference client, est accepte comme rejeu.
     */
    #[Test]
    public function un_scan_hors_ligne_rejoue_sous_la_meme_reference_est_accepte(): void
    {
        $ticket = $this->issueTicket();
        $qr = $this->qrOf($ticket);

        $payload = [
            'code' => $qr,
            'trip_id' => $this->trip->id,
            'client_reference' => 'SCAN-TABLETTE-01-000042',
        ];

        $this->actingAs($this->agent)->postJson('/api/tickets/validate', $payload)->assertOk();

        $this->actingAs($this->agent)->postJson('/api/tickets/validate', $payload)
            ->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.is_replay', true);
    }

    /**
     * Un rejeu portant une **autre** reference est bien une seconde
     * presentation : c'est le cas d'un billet photographie et re-presente par
     * un tiers.
     */
    #[Test]
    public function un_scan_avec_une_autre_reference_client_reste_un_second_scan(): void
    {
        $ticket = $this->issueTicket();
        $qr = $this->qrOf($ticket);

        $this->actingAs($this->agent)->postJson('/api/tickets/validate', [
            'code' => $qr,
            'client_reference' => 'SCAN-A',
        ])->assertOk();

        $this->actingAs($this->agent)->postJson('/api/tickets/validate', [
            'code' => $qr,
            'client_reference' => 'SCAN-B',
        ])->assertStatus(409)->assertJsonPath('data.outcome', ScanOutcome::AlreadyUsed->value);
    }

    #[Test]
    public function une_signature_falsifiee_est_refusee(): void
    {
        $ticket = $this->issueTicket();
        $qr = $this->qrOf($ticket);

        // On garde le contenu, on remplace la signature.
        $forged = substr($qr, 0, strrpos($qr, '|') + 1).str_repeat('f', 32);

        $this->actingAs($this->agent)
            ->postJson('/api/tickets/validate', ['code' => $forged])
            ->assertStatus(422)
            ->assertJsonPath('data.outcome', ScanOutcome::InvalidSignature->value);

        $this->assertNull($ticket->refresh()->scanned_at);
    }

    /**
     * Un QR authentique presente au mauvais bus est refuse : techniquement
     * valide, il ferait monter le voyageur dans le mauvais vehicule.
     */
    #[Test]
    public function un_billet_d_un_autre_depart_est_refuse(): void
    {
        $ticket = $this->issueTicket();

        $otherTrip = Trip::factory()
            ->departingAt(Carbon::now()->addHour())
            ->create(['company_id' => $this->company->id]);

        $this->actingAs($this->agent)->postJson('/api/tickets/validate', [
            'code' => $this->qrOf($ticket),
            'trip_id' => $otherTrip->id,
        ])->assertStatus(409)->assertJsonPath('data.outcome', ScanOutcome::WrongTrip->value);
    }

    #[Test]
    public function un_billet_inconnu_est_refuse(): void
    {
        $this->actingAs($this->agent)
            ->postJson('/api/tickets/validate', ['code' => 'BIL-ZZZZZZ'])
            ->assertStatus(404)
            ->assertJsonPath('data.outcome', ScanOutcome::NotFound->value);
    }

    #[Test]
    public function un_billet_annule_est_refuse(): void
    {
        $ticket = $this->issueTicket();
        $qr = $this->qrOf($ticket);

        app(BookingService::class)->cancel(Booking::findOrFail($ticket->booking_id), 'Desistement');

        $this->actingAs($this->agent)
            ->postJson('/api/tickets/validate', ['code' => $qr])
            ->assertStatus(409)
            ->assertJsonPath('data.outcome', ScanOutcome::Cancelled->value);
    }

    #[Test]
    public function un_billet_d_un_depart_annule_est_refuse(): void
    {
        $ticket = $this->issueTicket();

        $this->trip->update(['status' => TripStatus::Cancelled, 'cancelled_at' => Carbon::now()]);

        $this->actingAs($this->agent)
            ->postJson('/api/tickets/validate', ['code' => $this->qrOf($ticket)])
            ->assertStatus(409)
            ->assertJsonPath('data.outcome', ScanOutcome::TripCancelled->value);
    }

    /**
     * Hors fenetre d'embarquement, le scan est refuse avec un motif explicite :
     * un billet de la veille presente au mauvais bus doit se voir.
     */
    #[Test]
    public function un_scan_hors_fenetre_d_embarquement_est_refuse(): void
    {
        $ticket = $this->issueTicket();
        $qr = $this->qrOf($ticket);

        // Trois heures avant le depart : la fenetre n'ouvre que deux heures avant.
        Carbon::setTestNow($this->trip->departs_at->copy()->subHours(3));

        $this->actingAs($this->agent)
            ->postJson('/api/tickets/validate', ['code' => $qr])
            ->assertStatus(409)
            ->assertJsonPath('data.outcome', ScanOutcome::OutsideBoardingWindow->value);

        Carbon::setTestNow();
    }

    #[Test]
    public function le_scan_exige_la_permission_de_validation(): void
    {
        $ticket = $this->issueTicket();
        $passenger = $this->userWithRole('passenger');

        $this->actingAs($passenger)
            ->postJson('/api/tickets/validate', ['code' => $this->qrOf($ticket)])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }
}
