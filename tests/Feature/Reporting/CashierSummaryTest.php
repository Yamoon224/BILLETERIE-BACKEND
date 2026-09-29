<?php

namespace Tests\Feature\Reporting;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
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
 * Cloture de caisse : ce qu'un agent a lui-meme encaisse aujourd'hui.
 */
class CashierSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(NotificationSenderContract::class, new ArrayNotificationSender);

        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->withCapacity(60)->create(['company_id' => $company->id]);
        $itinerary = Itinerary::factory()->create(['company_id' => $company->id]);

        $this->trip = Trip::factory()
            ->onVehicle($vehicle)
            ->departingAt(Carbon::now()->addHours(5))
            ->create(['itinerary_id' => $itinerary->id, 'company_id' => $company->id, 'price' => 2000]);

        $this->agent = $this->userWithRole('agent', $company);
    }

    #[Test]
    public function la_cloture_additionne_les_ventes_en_especes_du_jour(): void
    {
        $this->actingAs($this->agent)->postJson('/api/counter-sales', [
            'trip_id' => $this->trip->id,
            'customer_name' => 'Yao Kouassi',
            'customer_phone' => '+2250500000001',
            'payment_method' => 'cash',
            'passengers' => [['seat_number' => '1A', 'name' => 'Yao Kouassi']],
        ])->assertCreated();

        $this->actingAs($this->agent)->getJson('/api/me/cashier-summary')
            ->assertOk()
            ->assertJsonPath('data.tickets_sold', 1)
            ->assertJsonPath('data.cash_amount', 2000)
            ->assertJsonPath('data.mobile_money_amount', 0)
            ->assertJsonPath('data.total_amount', 2000);
    }

    #[Test]
    public function un_agent_ne_voit_pas_les_ventes_d_un_collegue(): void
    {
        $other = $this->userWithRole('agent', $this->agent->company);

        $this->actingAs($other)->postJson('/api/counter-sales', [
            'trip_id' => $this->trip->id,
            'customer_name' => 'Awa Kone',
            'customer_phone' => '+2250500000002',
            'payment_method' => 'cash',
            'passengers' => [['seat_number' => '1B', 'name' => 'Awa Kone']],
        ])->assertCreated();

        $this->actingAs($this->agent)->getJson('/api/me/cashier-summary')
            ->assertOk()
            ->assertJsonPath('data.tickets_sold', 0)
            ->assertJsonPath('data.total_amount', 0);
    }
}
