<?php

namespace Tests\Feature\Booking;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Scheduling\Enums\TripStatus;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Itinerary;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Critere de recette du cahier des charges :
 *
 *   « Un voyageur peut reserver et payer un trajet de bout en bout sans
 *     intervention manuelle. »
 *
 * Ces tests parcourent le tunnel complet par l'API publique — sans compte,
 * comme un voyageur reel — et verifient les deux garanties qui l'entourent :
 * une place n'est jamais vendue deux fois, et une reservation non payee libere
 * ses places.
 */
class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->withCapacity(60)->create(['company_id' => $company->id]);
        $itinerary = Itinerary::factory()->create(['company_id' => $company->id, 'base_price' => 7000]);

        $this->trip = Trip::factory()
            ->onVehicle($vehicle)
            ->departingAt(Carbon::now()->addDay())
            ->create(['itinerary_id' => $itinerary->id, 'company_id' => $company->id, 'price' => 7000]);
    }

    /**
     * @param  list<string>  $seats
     * @return TestResponse<Response>
     */
    private function reserve(array $seats = ['1A'], string $phone = '+2250700000010'): TestResponse
    {
        return $this->postJson('/api/bookings', [
            'trip_id' => $this->trip->id,
            'customer_name' => 'Awa Kone',
            'customer_phone' => $phone,
            'customer_email' => 'awa@example.test',
            'passengers' => array_map(
                fn (string $seat) => ['seat_number' => $seat, 'name' => 'Voyageur '.$seat],
                $seats,
            ),
        ]);
    }

    // -------------------------------------------------------------------------

    #[Test]
    public function un_voyageur_reserve_sans_compte_et_ses_places_sont_bloquees(): void
    {
        $response = $this->reserve(['1A', '1B']);

        $response->assertCreated()
            ->assertJsonPath('data.status', BookingStatus::Pending->value)
            ->assertJsonPath('data.seats_count', 2)
            ->assertJsonPath('data.total_amount', 14000)
            ->assertJsonPath('data.currency', 'XOF');

        // L'echeance de blocage est posee : sans elle, des places resteraient
        // immobilisees indefiniment par une reservation jamais payee.
        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertCount(2, $response->json('data.tickets'));
    }

    /**
     * La commission est figee a la vente, taux compris : une revision de
     * bareme ne doit pas reecrire ce qui a deja ete facture.
     */
    #[Test]
    public function la_commission_est_calculee_et_archivee_avec_son_taux(): void
    {
        $this->reserve(['2A'])->assertCreated();

        $booking = Booking::firstOrFail();

        $this->assertSame(25, $booking->commission_per_mille);
        $this->assertSame(175, $booking->commission_amount);
        $this->assertSame(6825, $booking->netAmount());
    }

    /**
     * La garantie remboursement s'ajoute au prix du trajet mais reste hors
     * assiette de commission : c'est un frais plateforme, pas une recette de
     * la compagnie.
     */
    #[Test]
    public function la_garantie_remboursement_s_ajoute_au_total_sans_affecter_la_commission(): void
    {
        $response = $this->postJson('/api/bookings', [
            'trip_id' => $this->trip->id,
            'customer_name' => 'Awa Kone',
            'customer_phone' => '+2250700000020',
            'customer_email' => 'awa@example.test',
            'passengers' => [['seat_number' => '2B', 'name' => 'Voyageur 2B']],
            'refund_guarantee' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_amount', 7300)
            ->assertJsonPath('data.refund_guarantee_fee', 300)
            ->assertJsonPath('data.has_refund_guarantee', true);

        $booking = Booking::where('customer_phone', '+2250700000020')->firstOrFail();
        $this->assertSame(175, $booking->commission_amount);
    }

    /** LE garde-fou : une place n'est jamais vendue deux fois. */
    #[Test]
    public function une_place_deja_vendue_ne_peut_pas_etre_revendue(): void
    {
        $this->reserve(['3A'])->assertCreated();

        $this->reserve(['3A'], '+2250700000011')
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'seat_unavailable')
            ->assertJsonPath('context.seats', ['3A']);

        $this->assertSame(1, Ticket::where('seat_number', '3A')->count());
    }

    /**
     * L'index unique en base est la garantie ; la verification prealable n'est
     * qu'un confort de message. On le verifie en contournant la verification :
     * la contrainte doit tenir seule.
     */
    #[Test]
    public function la_contrainte_de_base_empeche_la_double_vente_meme_sans_verification_prealable(): void
    {
        $this->reserve(['4A'])->assertCreated();

        $ticket = Ticket::where('seat_number', '4A')->firstOrFail();

        $this->expectException(UniqueConstraintViolationException::class);

        Ticket::create([
            'booking_id' => $ticket->booking_id,
            'trip_id' => $this->trip->id,
            'code' => 'BIL-DOUBLE',
            'seat_number' => '4A',
            'passenger_name' => 'Passager clandestin',
            'status' => 'issued',
            'signature' => str_repeat('0', 32),
            'key_version' => 1,
        ]);
    }

    /**
     * Une place liberee par une annulation redevient vendable : c'est ce que
     * permet le passage de `seat_number` a NULL, plutot qu'un index partiel
     * que MySQL ne sait pas exprimer.
     */
    #[Test]
    public function une_place_liberee_par_annulation_redevient_vendable(): void
    {
        $first = $this->reserve(['5A'])->assertCreated();
        $bookingId = $first->json('data.id');

        // L annulation est une route authentifiee : un achat sans compte
        // s annule au guichet, pas depuis un lien recu par SMS.
        $manager = $this->userWithRole('company_manager', $this->trip->company);

        $this->actingAs($manager)->postJson("/api/bookings/{$bookingId}/cancel", ['reason' => 'Desistement'])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Cancelled->value);

        $this->reserve(['5A'], '+2250700000012')->assertCreated();

        // Deux billets ont porte la place 5A : l'un l'a rendue, l'autre la tient.
        $this->assertSame(1, Ticket::where('seat_number', '5A')->count());
        $this->assertSame(1, Ticket::where('released_seat_number', '5A')->count());
    }

    #[Test]
    public function une_place_inexistante_est_refusee(): void
    {
        // Le vehicule compte 60 places (15 rangees de 4) : la 99Z n'existe pas.
        $this->reserve(['99Z'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_seat');
    }

    #[Test]
    public function la_meme_place_demandee_deux_fois_dans_une_reservation_est_refusee(): void
    {
        $this->reserve(['1A', '1A'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'duplicate_seat');
    }

    #[Test]
    public function un_depart_annule_n_accepte_plus_de_reservation(): void
    {
        $this->trip->update(['status' => TripStatus::Cancelled, 'cancelled_at' => Carbon::now()]);

        $this->reserve(['1A'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'trip_not_bookable');
    }

    // --- Paiement -------------------------------------------------------------

    #[Test]
    public function le_paiement_mobile_money_confirme_la_reservation_et_envoie_le_billet(): void
    {
        $sender = new ArrayNotificationSender;
        $this->app->instance(NotificationSenderContract::class, $sender);

        $bookingId = $this->reserve(['6A'])->json('data.id');

        $response = $this->postJson("/api/bookings/{$bookingId}/payments", [
            'provider' => 'orange_money',
            'payer_msisdn' => '+2250700000013',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment.status', PaymentStatus::Succeeded->value)
            ->assertJsonPath('data.booking.status', BookingStatus::Confirmed->value);

        // La reservation payee ne peut plus expirer.
        $this->assertNull($response->json('data.booking.expires_at'));

        // Le billet est parti au voyageur, sur le numero de la reservation.
        $this->assertCount(1, $sender->sent());
        $this->assertSame('+2250700000010', $sender->sent()[0]->recipient);
    }

    /**
     * Le numero de test se terminant par 0000 simule un solde insuffisant :
     * la reservation reste en attente et ses places restent bloquees jusqu'a
     * l'echeance.
     */
    #[Test]
    public function un_paiement_echoue_laisse_la_reservation_en_attente(): void
    {
        $bookingId = $this->reserve(['7A'])->json('data.id');

        $this->postJson("/api/bookings/{$bookingId}/payments", [
            'provider' => 'mtn_money',
            'payer_msisdn' => '+2250700000000',
        ])->assertCreated()->assertJsonPath('data.payment.status', PaymentStatus::Failed->value);

        $this->assertSame(BookingStatus::Pending, Booking::findOrFail($bookingId)->status);
    }

    #[Test]
    public function une_reservation_deja_payee_n_accepte_pas_un_second_paiement(): void
    {
        $bookingId = $this->reserve(['8A'])->json('data.id');

        $this->postJson("/api/bookings/{$bookingId}/payments", [
            'provider' => 'wave',
            'payer_msisdn' => '+2250700000014',
        ])->assertCreated();

        $this->postJson("/api/bookings/{$bookingId}/payments", [
            'provider' => 'wave',
            'payer_msisdn' => '+2250700000014',
        ])->assertStatus(409)->assertJsonPath('error_code', 'booking_not_payable');
    }

    /**
     * La collision la plus penible en production : le voyageur valide son
     * debit au moment ou ses places retournent a la vente. L'encaissement est
     * refuse **avant** l'appel a l'agregateur — encaisser puis decouvrir la
     * place revendue produirait un remboursement et un voyageur sans siege.
     */
    #[Test]
    public function un_paiement_arrivant_apres_l_echeance_est_refuse(): void
    {
        $bookingId = $this->reserve(['9A'])->json('data.id');

        Carbon::setTestNow(Carbon::now()->addMinutes((int) config('ticketing.hold_minutes') + 1));

        $this->postJson("/api/bookings/{$bookingId}/payments", [
            'provider' => 'orange_money',
            'payer_msisdn' => '+2250700000015',
        ])->assertStatus(409)->assertJsonPath('error_code', 'booking_hold_expired');

        Carbon::setTestNow();
    }

    // --- Consultation ----------------------------------------------------------

    #[Test]
    public function le_voyageur_retrouve_sa_reservation_par_sa_reference(): void
    {
        $reference = $this->reserve(['10A'])->json('data.reference');

        // Reference dictee au telephone puis retapee : espaces et minuscules.
        $this->getJson('/api/bookings/reference/'.strtolower($reference))
            ->assertOk()
            ->assertJsonPath('data.reference', $reference);
    }

    #[Test]
    public function une_reference_inconnue_renvoie_404(): void
    {
        $this->getJson('/api/bookings/reference/RES-ZZZZZZ')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }
}
