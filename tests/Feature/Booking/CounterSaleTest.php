<?php

namespace Tests\Feature\Booking;

use App\Domains\Booking\Enums\BookingChannel;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Itinerary;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vente au guichet, en ligne. La vente hors ligne est couverte par
 * OfflineSyncTest.
 */
class CounterSaleTest extends TestCase
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
            ->departingAt(Carbon::now()->addHours(5))
            ->create(['itinerary_id' => $itinerary->id, 'company_id' => $company->id, 'price' => 6000]);

        $this->agent = $this->userWithRole('agent', $company);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'trip_id' => $this->trip->id,
            'customer_name' => 'Yao Kouassi',
            'customer_phone' => '+2250500000001',
            'payment_method' => 'cash',
            'passengers' => [
                ['seat_number' => '1A', 'name' => 'Yao Kouassi'],
                ['seat_number' => '1B', 'name' => 'Aya Kouassi'],
            ],
            ...$overrides,
        ];
    }

    #[Test]
    public function une_vente_en_especes_est_confirmee_et_encaissee_immediatement(): void
    {
        $sender = new ArrayNotificationSender;
        $this->app->instance(NotificationSenderContract::class, $sender);

        $response = $this->actingAs($this->agent)->postJson('/api/counter-sales', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.booking.status', BookingStatus::Confirmed->value)
            ->assertJsonPath('data.booking.channel', BookingChannel::Counter->value)
            ->assertJsonPath('data.booking.total_amount', 12000)
            ->assertJsonPath('data.booking.expires_at', null)
            ->assertJsonPath('data.payment.method', 'cash')
            ->assertJsonPath('data.payment.status', 'succeeded');

        $booking = Booking::firstOrFail();
        $this->assertSame($this->agent->id, $booking->sold_by_user_id);
        $this->assertSame($this->agent->id, Payment::firstOrFail()->collected_by_user_id);

        // Un seul SMS, meme pour deux voyageurs : le billet part au payeur.
        $this->assertCount(1, $sender->sent());
    }

    #[Test]
    public function une_vente_au_guichet_en_mobile_money_exige_le_numero_du_payeur(): void
    {
        $this->actingAs($this->agent)
            ->postJson('/api/counter-sales', $this->payload(['payment_method' => 'mobile_money']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_provider', 'payer_msisdn']);
    }

    /** Une reponse perdue puis rejouee ne vend pas deux fois. */
    #[Test]
    public function une_vente_rejouee_sous_la_meme_reference_client_n_est_pas_dupliquee(): void
    {
        $payload = $this->payload(['client_reference' => 'GUICHET-01-000123']);

        $first = $this->actingAs($this->agent)->postJson('/api/counter-sales', $payload)->assertCreated();
        $second = $this->actingAs($this->agent)->postJson('/api/counter-sales', $payload)->assertCreated();

        $this->assertSame($first->json('data.booking.id'), $second->json('data.booking.id'));
        $this->assertSame(1, Booking::count());
        $this->assertSame(1, Payment::count());
    }

    #[Test]
    public function un_gestionnaire_sans_role_agent_ne_vend_pas_au_guichet(): void
    {
        $manager = $this->userWithRole('company_manager', $this->trip->company);

        $this->actingAs($manager)
            ->postJson('/api/counter-sales', $this->payload())
            ->assertStatus(403);
    }
}
