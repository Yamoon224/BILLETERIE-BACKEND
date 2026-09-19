<?php

namespace App\Domains\Booking\DTOs;

/**
 * Un voyageur et la place qu'il occupe, tels que soumis a la vente.
 *
 * Le nom est obligatoire, le telephone non : au guichet, un pere achete
 * quatre places pour sa famille et ne donne qu'un numero, le sien. Exiger un
 * numero par voyageur allongerait chaque vente d'une minute pour une donnee
 * que personne ne verifiera. La piece d'identite suit la meme logique :
 * facultative, et jamais bloquante pour la vente.
 */
final readonly class PassengerDraft
{
    public function __construct(
        public string $seatNumber,
        public string $name,
        public ?string $phone = null,
        public ?string $idNumber = null,
    ) {}

    /** @param  array{seat_number: string, name: string, phone?: string|null, id_number?: string|null}  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            seatNumber: strtoupper(trim($data['seat_number'])),
            name: trim($data['name']),
            phone: isset($data['phone']) && $data['phone'] !== '' ? trim((string) $data['phone']) : null,
            idNumber: isset($data['id_number']) && trim((string) $data['id_number']) !== '' ? trim((string) $data['id_number']) : null,
        );
    }
}
