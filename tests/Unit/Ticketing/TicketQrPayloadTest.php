<?php

namespace Tests\Unit\Ticketing;

use App\Domains\Ticketing\DTOs\TicketQrPayload;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Le contenu du QR est un format public : l'application agent le relit hors
 * ligne. Ces tests le figent — le changer casserait tous les billets deja
 * imprimes.
 */
class TicketQrPayloadTest extends TestCase
{
    private function payload(): TicketQrPayload
    {
        return new TicketQrPayload('BIL-K7M3XZ', 'DEP-260906-A7X2QW', '12A', 1789000000);
    }

    #[Test]
    public function il_encode_et_relit_le_meme_contenu(): void
    {
        $encoded = $this->payload()->encode('a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', 1);

        $decoded = TicketQrPayload::decode($encoded);

        $this->assertSame('BIL-K7M3XZ', $decoded['payload']->ticketCode);
        $this->assertSame('12A', $decoded['payload']->seatNumber);
        $this->assertSame(1, $decoded['key_version']);
        $this->assertSame('a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', $decoded['signature']);
    }

    /**
     * La chaine signee exclut la signature et la version : celle-ci voyage en
     * clair a cote, pour que le verificateur sache quelle cle essayer avant
     * meme d'avoir verifie quoi que ce soit.
     */
    #[Test]
    public function la_chaine_canonique_ne_contient_ni_signature_ni_version(): void
    {
        $canonical = $this->payload()->canonical();

        $this->assertSame('B1|BIL-K7M3XZ|DEP-260906-A7X2QW|12A|1789000000', $canonical);
    }

    #[Test]
    public function il_refuse_un_contenu_qui_n_est_pas_un_billet(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TicketQrPayload::decode('https://example.test/promo');
    }

    #[Test]
    public function il_refuse_un_contenu_tronque(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TicketQrPayload::decode('B1|BIL-K7M3XZ|DEP-260906-A7X2QW');
    }
}
