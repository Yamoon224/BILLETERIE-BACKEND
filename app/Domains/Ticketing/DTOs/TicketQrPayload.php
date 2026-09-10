<?php

namespace App\Domains\Ticketing\DTOs;

use App\Models\Ticket;
use InvalidArgumentException;

/**
 * Contenu d'un QR code de billet.
 *
 * Le QR est **autoportant** : il contient tout ce qu'il faut pour verifier
 * l'authenticite d'un billet sans reseau. C'est une exigence directe du cahier
 * des charges — l'application agent doit fonctionner hors ligne — et cela
 * change la nature du QR : ce n'est pas un identifiant a resoudre cote
 * serveur, c'est une donnee signee.
 *
 * Conséquence importante : la signature prouve que le billet a bien ete emis
 * par la plateforme, **pas** qu'il n'a pas deja servi. La non-reutilisation se
 * verifie en base (voir TicketValidationService) ; hors ligne, elle se verifie
 * contre le journal local de la tablette, puis se reconcilie au retour du
 * reseau. Confondre les deux garanties serait la faille la plus couteuse du
 * systeme.
 *
 * Format, volontairement court — un QR dense est illisible sur un ticket
 * thermique et sous une camera bas de gamme :
 *
 *   B1|CODE|TRIP_REF|SEAT|DEPART_TS|KEY_VERSION|SIGNATURE
 */
final readonly class TicketQrPayload
{
    public const PREFIX = 'B1';

    private const SEPARATOR = '|';

    public function __construct(
        public string $ticketCode,
        public string $tripReference,
        public string $seatNumber,
        /** Horodatage Unix du depart : borne la validite sans appel reseau. */
        public int $departsAtTimestamp,
    ) {}

    public static function fromTicket(Ticket $ticket, string $tripReference, int $departsAtTimestamp): self
    {
        return new self(
            ticketCode: $ticket->code,
            tripReference: $tripReference,
            seatNumber: $ticket->displaySeatNumber() ?? '',
            departsAtTimestamp: $departsAtTimestamp,
        );
    }

    /**
     * Chaine canonique soumise a la signature.
     *
     * Elle exclut la signature elle-meme et la version de cle : la version
     * voyage en clair a cote, pour que le verificateur sache quelle cle
     * essayer avant meme d'avoir verifie quoi que ce soit.
     */
    public function canonical(): string
    {
        return implode(self::SEPARATOR, [
            self::PREFIX,
            $this->ticketCode,
            $this->tripReference,
            $this->seatNumber,
            (string) $this->departsAtTimestamp,
        ]);
    }

    /** Contenu complet encode dans le QR. */
    public function encode(string $signature, int $keyVersion): string
    {
        return implode(self::SEPARATOR, [
            $this->canonical(),
            (string) $keyVersion,
            $signature,
        ]);
    }

    /**
     * Relit un QR scanne.
     *
     * @return array{payload: self, key_version: int, signature: string}
     *
     * @throws InvalidArgumentException si la chaine n'a pas la forme attendue
     */
    public static function decode(string $raw): array
    {
        $parts = explode(self::SEPARATOR, trim($raw));

        // 5 champs canoniques + version de cle + signature.
        if (count($parts) !== 7 || $parts[0] !== self::PREFIX) {
            throw new InvalidArgumentException('Contenu de QR code non reconnu.');
        }

        return [
            'payload' => new self(
                ticketCode: $parts[1],
                tripReference: $parts[2],
                seatNumber: $parts[3],
                departsAtTimestamp: (int) $parts[4],
            ),
            'key_version' => (int) $parts[5],
            'signature' => $parts[6],
        ];
    }
}
