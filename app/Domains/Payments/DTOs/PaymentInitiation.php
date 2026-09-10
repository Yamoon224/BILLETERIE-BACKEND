<?php

namespace App\Domains\Payments\DTOs;

use App\Domains\Payments\Enums\PaymentStatus;

/**
 * Reponse immediate de l'agregateur a une demande d'encaissement.
 *
 * `status` est le plus souvent `Pending` : un debit mobile money suppose que
 * le voyageur compose son code sur son telephone, ce qui prend le temps que
 * cela prend. L'issue arrive plus tard, par rappel (webhook).
 *
 * `instruction` porte ce que le voyageur doit faire — « composez *144# et
 * validez » — texte que seul l'agregateur connait et qui varie par operateur.
 * Le rendre generique cote interface reviendrait a le reecrire a chaque
 * changement d'operateur.
 */
final readonly class PaymentInitiation
{
    /** @param  array<string, mixed>  $rawPayload */
    public function __construct(
        public string $externalReference,
        public PaymentStatus $status,
        public ?string $instruction = null,
        public ?string $failureReason = null,
        public array $rawPayload = [],
    ) {}
}
