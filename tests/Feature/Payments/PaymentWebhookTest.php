<?php

namespace Tests\Feature\Payments;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Rappels de l'agregateur de paiement.
 *
 * C'est la surface la plus exposee de l'API : une route publique qui confirme
 * des encaissements. Sa seule protection est la signature — ces tests
 * verifient qu'elle ne peut pas etre contournee, et que le chemin nominal
 * confirme bien la reservation.
 */
class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('payments.webhook_secret'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function postCallback(array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            '/api/payments/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature ?? $this->sign($body),
            ],
            content: $body,
        );
    }

    private function pendingPayment(string $externalReference): Payment
    {
        $booking = Booking::factory()->pending()->create();

        return Payment::factory()->pendingMobileMoney($externalReference)->create([
            'booking_id' => $booking->id,
            'amount' => $booking->total_amount,
        ]);
    }

    // -------------------------------------------------------------------------

    #[Test]
    public function un_rappel_signe_confirme_la_reservation_et_envoie_le_billet(): void
    {
        $sender = new ArrayNotificationSender;
        $this->app->instance(NotificationSenderContract::class, $sender);

        $payment = $this->pendingPayment('SIM-ABCDEF123456');

        $this->postCallback(['reference' => 'SIM-ABCDEF123456', 'status' => 'succeeded'])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Succeeded->value);

        $this->assertSame(PaymentStatus::Succeeded, $payment->refresh()->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(BookingStatus::Confirmed, $payment->booking->refresh()->status);
        $this->assertCount(1, $sender->sent());
    }

    #[Test]
    public function un_rappel_d_echec_laisse_la_reservation_en_attente(): void
    {
        $payment = $this->pendingPayment('SIM-FAIL0001');

        $this->postCallback([
            'reference' => 'SIM-FAIL0001',
            'status' => 'failed',
            'reason' => 'Solde insuffisant',
        ])->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('Solde insuffisant', $payment->failure_reason);
        $this->assertSame(BookingStatus::Pending, $payment->booking->refresh()->status);
    }

    /** Sans signature valide, rien ne passe. */
    #[Test]
    public function un_rappel_non_signe_est_refuse(): void
    {
        $payment = $this->pendingPayment('SIM-UNSIGNED');

        $body = json_encode(['reference' => 'SIM-UNSIGNED', 'status' => 'succeeded'], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/payments/webhook',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: $body,
        )->assertStatus(401)->assertJsonPath('error_code', 'invalid_webhook_signature');

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    #[Test]
    public function un_rappel_mal_signe_est_refuse(): void
    {
        $payment = $this->pendingPayment('SIM-BADSIG');

        $this->postCallback(
            ['reference' => 'SIM-BADSIG', 'status' => 'succeeded'],
            signature: str_repeat('0', 64),
        )->assertStatus(401);

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    /**
     * La signature porte sur le corps brut : modifier le contenu apres
     * signature doit invalider le rappel, sinon un intermediaire pourrait
     * changer le statut sans changer la signature.
     */
    #[Test]
    public function un_corps_modifie_apres_signature_est_refuse(): void
    {
        $payment = $this->pendingPayment('SIM-TAMPER');

        $signature = $this->sign(json_encode(['reference' => 'SIM-TAMPER', 'status' => 'failed'], JSON_THROW_ON_ERROR));

        $this->postCallback(['reference' => 'SIM-TAMPER', 'status' => 'succeeded'], signature: $signature)
            ->assertStatus(401);

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    #[Test]
    public function une_reference_inconnue_renvoie_404(): void
    {
        $this->postCallback(['reference' => 'SIM-INCONNUE', 'status' => 'succeeded'])
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'unknown_payment_reference');
    }

    /**
     * Les agregateurs rejouent volontiers leurs rappels. Le premier verdict
     * fait foi : le rejouer reecrirait une date d'encaissement deja
     * comptabilisee et enverrait un second SMS pour un seul billet.
     */
    #[Test]
    public function un_rappel_rejoue_ne_reecrit_pas_l_encaissement(): void
    {
        $sender = new ArrayNotificationSender;
        $this->app->instance(NotificationSenderContract::class, $sender);

        $payment = $this->pendingPayment('SIM-REPLAY');

        $this->postCallback(['reference' => 'SIM-REPLAY', 'status' => 'succeeded'])->assertOk();
        $paidAt = $payment->refresh()->paid_at;

        $this->postCallback(['reference' => 'SIM-REPLAY', 'status' => 'failed'])->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame($paidAt?->toDateTimeString(), $payment->paid_at?->toDateTimeString());
        $this->assertCount(1, $sender->sent());
    }
}
