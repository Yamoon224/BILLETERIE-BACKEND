<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Booking\Contracts\BookingRepositoryContract;
use App\Domains\Booking\Http\Resources\BookingResource;
use App\Domains\Payments\Contracts\PaymentRepositoryContract;
use App\Domains\Payments\Http\Requests\InitiatePaymentRequest;
use App\Domains\Payments\Http\Resources\PaymentResource;
use App\Domains\Payments\Services\PaymentService;
use App\Domains\Shared\Exceptions\CompanyScopeViolationException;
use App\Domains\Shared\Support\CompanyScope;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentRepositoryContract $repository,
        private readonly BookingRepositoryContract $bookings,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PaymentResource::collection($this->repository->paginate(
            CompanyScope::apply(
                $request->only('booking_id', 'method', 'status', 'from', 'to', 'search', 'sort', 'direction'),
                $request->user(),
            ),
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Lance un paiement mobile money.
     *
     * Endpoint accessible sans authentification : un voyageur peut reserver et
     * payer sans creer de compte, ce qui est une exigence du parcours. La
     * reservation est designee par sa reference imprevisible, et le montant
     * vient d'elle — il n'y a donc rien a manipuler depuis l'exterieur.
     */
    public function initiate(InitiatePaymentRequest $request, Booking $booking): JsonResponse
    {
        $payment = $this->payments->payWithMobileMoney(
            $booking,
            $request->provider(),
            (string) $request->input('payer_msisdn'),
        );

        return response()->json([
            'data' => [
                'payment' => new PaymentResource($payment),
                'booking' => new BookingResource($this->bookings->findOrFail($booking->id)),
            ],
        ], 201);
    }

    public function show(Request $request, Payment $payment): PaymentResource
    {
        $payment->loadMissing(['booking', 'collectedBy']);

        if (! CompanyScope::allows($request->user(), $payment->booking->company_id)) {
            throw CompanyScopeViolationException::make();
        }

        return new PaymentResource($payment);
    }

    /**
     * Remboursement d'un encaissement acquis.
     *
     * Il ne libere pas les places : annuler la reservation est un geste
     * distinct, et les deux ne coincident pas toujours — un geste commercial
     * peut rembourser un billet que le voyageur utilisera quand meme.
     */
    public function refund(Request $request, Payment $payment): PaymentResource
    {
        $payment->loadMissing('booking');

        if (! CompanyScope::allows($request->user(), $payment->booking->company_id)) {
            throw CompanyScopeViolationException::make();
        }

        return new PaymentResource($this->payments->refund($payment));
    }
}
