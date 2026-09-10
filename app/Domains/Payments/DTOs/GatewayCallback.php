<?php

namespace App\Domains\Payments\DTOs;

use App\Domains\Payments\Enums\PaymentStatus;

/**
 * Rappel de l'agregateur, une fois traduit dans notre vocabulaire.
 *
 * La traduction se fait dans le pilote, jamais dans le service : chaque
 * agregateur nomme ses statuts a sa facon (`SUCCESSFUL`, `COMPLETED`, `OK`),
 * et laisser ces chaines remonter jusqu'au domaine y ferait entrer le
 * vocabulaire d'un prestataire qu'on est cense pouvoir remplacer.
 */
final readonly class GatewayCallback
{
    /** @param  array<string, mixed>  $rawPayload */
    public function __construct(
        public string $externalReference,
        public PaymentStatus $status,
        public ?string $failureReason = null,
        public ?string $payerMsisdn = null,
        public array $rawPayload = [],
    ) {}
}
