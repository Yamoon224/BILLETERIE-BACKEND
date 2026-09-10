<?php

namespace App\Domains\Payments\DTOs;

use App\Domains\Payments\Enums\MobileMoneyProvider;

/**
 * Demande d'encaissement transmise a l'agregateur.
 *
 * Ne contient aucune donnee de paiement : un montant, un numero de telephone,
 * un operateur et nos propres references. C'est tout ce dont un agregateur
 * mobile money a besoin, et c'est la traduction concrete de l'exigence du
 * cahier des charges — aucune donnee de paiement stockee en propre.
 */
final readonly class PaymentIntent
{
    public function __construct(
        public string $paymentReference,
        public string $bookingReference,
        public int $amount,
        public string $currency,
        public ?MobileMoneyProvider $provider,
        /** Numero mobile du payeur, au format international. */
        public ?string $payerMsisdn,
        public ?string $callbackUrl = null,
        public ?string $description = null,
    ) {}
}
