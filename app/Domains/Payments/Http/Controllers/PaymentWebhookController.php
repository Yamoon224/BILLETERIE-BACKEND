<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Payments\Contracts\PaymentGatewayContract;
use App\Domains\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Domains\Payments\Http\Resources\PaymentResource;
use App\Domains\Payments\Services\PaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rappel de l'agregateur de paiement.
 *
 * Deux points sont critiques ici, et ce sont les deux erreurs classiques des
 * integrations de webhook.
 *
 * **La signature est verifiee sur le corps brut**, tel qu'il est arrive. Un
 * JSON decode puis re-encode n'a plus le meme ordre de cles ni les memes
 * espaces : la signature calculee dessus ne correspondrait jamais, et la
 * tentation serait alors de desactiver la verification « le temps de
 * debugger ». Un endpoint d'encaissement non signe est une API ouverte pour
 * confirmer les paiements des autres.
 *
 * **La route est publique mais non authentifiee au sens applicatif.**
 * L'agregateur ne possede aucun compte : la signature *est* son
 * authentification. C'est aussi pourquoi cette route est exclue de toute
 * limitation par jeton — elle n'en a pas.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentGatewayContract $gateway,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = $request->header('X-Signature')
            ?? $request->header('X-Payment-Signature');

        if (! $this->gateway->verifyCallbackSignature($request->getContent(), $signature)) {
            throw InvalidWebhookSignatureException::make();
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $payment = $this->payments->handleCallback($this->gateway->parseCallback($payload));

        return response()->json(['data' => new PaymentResource($payment)]);
    }
}
