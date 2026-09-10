<?php

namespace App\Domains\Payments\Gateways;

use App\Domains\Payments\Contracts\PaymentGatewayContract;
use App\Domains\Payments\DTOs\GatewayCallback;
use App\Domains\Payments\DTOs\PaymentInitiation;
use App\Domains\Payments\DTOs\PaymentIntent;
use App\Domains\Payments\Enums\PaymentStatus;
use App\Domains\Shared\Support\Money;

/**
 * Agregateur simule : pilote du poste de developpement et de la suite de tests.
 *
 * Il rejoue le cycle complet sans aucun appel reseau. Deux comportements sont
 * pilotes par le numero du payeur, pour rendre les cas d'echec reproductibles
 * sans mock :
 *
 *   - un numero se terminant par `0000` echoue (solde insuffisant simule) ;
 *   - un numero se terminant par `9999` reste en attente indefiniment, ce qui
 *     permet de tester l'expiration d'une reservation dont le paiement ne
 *     revient jamais — le cas le plus penible en production et celui qu'on
 *     oublie le plus souvent de couvrir ;
 *   - tout autre numero aboutit immediatement.
 *
 * **Ecart assume avec un agregateur reel.** Un vrai prestataire repond
 * `Pending` et rappelle plus tard : le voyageur doit composer son code sur son
 * telephone. Ici, le succes est immediat, faute de quoi aucune demonstration
 * ne pourrait aller au bout sans declencher un webhook a la main. Le chemin du
 * rappel n'est pas pour autant fictif : `parseCallback` et
 * `verifyCallbackSignature` sont pleinement implementes, exerces par les
 * tests, et c'est ce meme chemin qu'empruntera l'agregateur reel.
 */
final class SimulatedMobileMoneyGateway implements PaymentGatewayContract
{
    private const FAILING_SUFFIX = '0000';

    private const PENDING_SUFFIX = '9999';

    public function name(): string
    {
        return 'simulated';
    }

    public function initiate(PaymentIntent $intent): PaymentInitiation
    {
        $reference = 'SIM-'.strtoupper(bin2hex(random_bytes(6)));
        $msisdn = (string) $intent->payerMsisdn;

        if (str_ends_with($msisdn, self::FAILING_SUFFIX)) {
            return new PaymentInitiation(
                externalReference: $reference,
                status: PaymentStatus::Failed,
                failureReason: 'Solde insuffisant (simulation).',
                rawPayload: ['simulated' => true, 'outcome' => 'failed'],
            );
        }

        if (str_ends_with($msisdn, self::PENDING_SUFFIX)) {
            return new PaymentInitiation(
                externalReference: $reference,
                status: PaymentStatus::Pending,
                instruction: 'Composez *144# puis validez le debit pour finaliser le paiement.',
                rawPayload: ['simulated' => true, 'outcome' => 'pending'],
            );
        }

        return new PaymentInitiation(
            externalReference: $reference,
            status: PaymentStatus::Succeeded,
            instruction: sprintf(
                'Debit de %s valide pour la reservation %s.',
                Money::format($intent->amount, $intent->currency),
                $intent->bookingReference,
            ),
            rawPayload: ['simulated' => true, 'outcome' => 'succeeded'],
        );
    }

    public function verifyCallbackSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('payments.webhook_secret');

        // Un secret vide n'autorise pas tout : sans secret configure, aucun
        // rappel ne peut etre authentifie, donc aucun n'est accepte. L'inverse
        // ferait d'une variable d'environnement oubliee une API d'encaissement
        // ouverte a tous.
        if ($secret === '' || $signature === null) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    public function parseCallback(array $payload): GatewayCallback
    {
        $status = match (strtolower((string) ($payload['status'] ?? ''))) {
            'succeeded', 'success', 'completed' => PaymentStatus::Succeeded,
            'failed', 'declined' => PaymentStatus::Failed,
            'expired', 'timeout' => PaymentStatus::Expired,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };

        return new GatewayCallback(
            externalReference: (string) ($payload['reference'] ?? ''),
            status: $status,
            failureReason: isset($payload['reason']) ? (string) $payload['reason'] : null,
            payerMsisdn: isset($payload['msisdn']) ? (string) $payload['msisdn'] : null,
            rawPayload: $payload,
        );
    }
}
