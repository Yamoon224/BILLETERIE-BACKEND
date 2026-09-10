<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Booking\Contracts\BookingRepositoryContract;
use App\Domains\Booking\Http\Requests\CounterSaleRequest;
use App\Domains\Booking\Http\Resources\BookingResource;
use App\Domains\Booking\Services\CounterSaleService;
use App\Domains\Payments\Http\Resources\PaymentResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Vente au guichet.
 *
 * La reponse porte la reservation **et** l'encaissement : l'agent doit voir
 * immediatement si le paiement mobile money est passe ou reste en attente, car
 * c'est lui qui remet — ou non — le ticket imprime au voyageur.
 */
class CounterSaleController extends Controller
{
    public function __construct(
        private readonly CounterSaleService $counter,
        private readonly BookingRepositoryContract $bookings,
    ) {}

    public function __invoke(CounterSaleRequest $request): JsonResponse
    {
        $agentId = $request->user()?->id;

        $result = $this->counter->sell(
            draft: $request->draft($agentId),
            method: $request->paymentMethod(),
            agentUserId: $agentId,
            provider: $request->paymentProvider(),
            payerMsisdn: $request->input('payer_msisdn'),
            paymentClientReference: $request->input('client_reference') !== null
                ? $request->input('client_reference').'-PAY'
                : null,
        );

        return response()->json([
            'data' => [
                'booking' => new BookingResource($this->bookings->findOrFail($result['booking']->id)),
                'payment' => new PaymentResource($result['payment']),
            ],
        ], 201);
    }
}
