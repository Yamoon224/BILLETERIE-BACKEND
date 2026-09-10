<?php

namespace Tests\Unit\Ticketing;

use App\Domains\Ticketing\DTOs\TicketQrPayload;
use App\Domains\Ticketing\Support\HmacTicketSigner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La signature est ce qui distingue un billet emis par la plateforme d'un QR
 * code fabrique par un tiers. Elle est verifiee hors ligne par l'application
 * agent : ces tests fixent son comportement, rotation de cle comprise.
 */
class HmacTicketSignerTest extends TestCase
{
    private function payload(string $seat = '12A'): TicketQrPayload
    {
        return new TicketQrPayload(
            ticketCode: 'BIL-K7M3XZ',
            tripReference: 'DEP-260906-A7X2QW',
            seatNumber: $seat,
            departsAtTimestamp: 1789000000,
        );
    }

    #[Test]
    public function une_signature_valide_est_acceptee(): void
    {
        $signer = new HmacTicketSigner;
        $payload = $this->payload();

        $signature = $signer->sign($payload);

        $this->assertTrue($signer->verify($payload, $signature, $signer->currentKeyVersion()));
    }

    /**
     * Le point central : la signature porte sur le contenu du billet, place
     * comprise. Changer la place d'un billet emis invalide sa signature.
     */
    #[Test]
    public function modifier_le_contenu_invalide_la_signature(): void
    {
        $signer = new HmacTicketSigner;

        $signature = $signer->sign($this->payload('12A'));

        $this->assertFalse($signer->verify($this->payload('12B'), $signature, 1));
    }

    #[Test]
    public function une_signature_forgee_est_refusee(): void
    {
        $signer = new HmacTicketSigner;

        $this->assertFalse($signer->verify($this->payload(), str_repeat('a', 32), 1));
    }

    /**
     * Une version de cle inconnue est refusee, et non retentee avec la cle
     * courante : sans cela, un attaquant annoncant « version 99 » ferait
     * verifier sa signature contre le secret en service.
     */
    #[Test]
    public function une_version_de_cle_inconnue_est_refusee(): void
    {
        $signer = new HmacTicketSigner;
        $payload = $this->payload();

        $signature = $signer->sign($payload);

        $this->assertFalse($signer->verify($payload, $signature, 99));
    }

    /**
     * Une rotation de secret ne doit pas invalider les billets deja vendus —
     * y compris celui du voyageur qui monte a l'instant.
     */
    #[Test]
    public function une_rotation_de_cle_laisse_verifiables_les_billets_anterieurs(): void
    {
        $signer = new HmacTicketSigner;
        $payload = $this->payload();

        $oldKey = (string) config('ticketing.signing.key');
        $oldSignature = $signer->sign($payload);

        // Rotation : nouvelle cle en service, ancienne conservee en version 1.
        config([
            'ticketing.signing.key' => 'base64:'.base64_encode(random_bytes(32)),
            'ticketing.signing.version' => 2,
            'ticketing.signing.accepted_versions' => [1, 2],
            'ticketing.signing.previous_keys' => ['1' => $oldKey],
        ]);

        $rotated = new HmacTicketSigner;

        $this->assertSame(2, $rotated->currentKeyVersion());
        $this->assertTrue($rotated->verify($payload, $oldSignature, 1));
        $this->assertNotSame($oldSignature, $rotated->sign($payload));
    }

    #[Test]
    public function la_signature_reste_assez_courte_pour_un_qr_lisible(): void
    {
        // 128 bits conserves sur 256 : chaque caractere retire abaisse la
        // densite du QR, qui doit rester lisible sur un ticket thermique.
        $this->assertSame(32, strlen((new HmacTicketSigner)->sign($this->payload())));
    }
}
