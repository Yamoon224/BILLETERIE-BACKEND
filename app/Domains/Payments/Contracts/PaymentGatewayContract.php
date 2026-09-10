<?php

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\DTOs\GatewayCallback;
use App\Domains\Payments\DTOs\PaymentInitiation;
use App\Domains\Payments\DTOs\PaymentIntent;

/**
 * Agregateur de paiement mobile money.
 *
 * Le cahier des charges impose explicitement de passer par un agregateur tiers
 * plutot que de brancher chaque operateur separement. Ce contrat en est la
 * traduction : le domaine ne connait ni Orange, ni MTN, ni Wave — il connait
 * une intention d'encaissement et un rappel.
 *
 * Concretement, changer de prestataire consiste a ecrire une classe et a
 * changer une ligne de `config/payments.php`. Aucun service metier, aucun
 * controleur, aucune migration n'est touche.
 */
interface PaymentGatewayContract
{
    /** Identifiant du pilote, archive avec chaque encaissement. */
    public function name(): string;

    /** Demande un encaissement et rend la reponse immediate de l'agregateur. */
    public function initiate(PaymentIntent $intent): PaymentInitiation;

    /**
     * Le rappel vient-il reellement de l'agregateur ?
     *
     * La verification porte sur le **corps brut** et non sur le tableau
     * decode : re-serialiser un JSON change l'ordre des cles et les espaces,
     * et une signature calculee sur une chaine reconstruite ne correspondra
     * jamais. C'est l'erreur classique des integrations de webhook.
     */
    public function verifyCallbackSignature(string $rawBody, ?string $signature): bool;

    /**
     * Traduit un rappel dans le vocabulaire du domaine.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseCallback(array $payload): GatewayCallback;
}
