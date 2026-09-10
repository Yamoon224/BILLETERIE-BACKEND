<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Booking\Contracts\BookingRepositoryContract;
use App\Domains\Booking\Http\Requests\StoreBookingRequest;
use App\Domains\Booking\Http\Resources\BookingResource;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Shared\Exceptions\CompanyScopeViolationException;
use App\Domains\Shared\Support\CompanyScope;
use App\Domains\Shared\Support\Reference;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Reservations.
 *
 * La consultation par reference est **publique** : un voyageur qui a achete
 * sans compte doit pouvoir retrouver son billet avec la reference recue par
 * SMS. C'est defendable parce que la reference est imprevisible (voir
 * `Reference`) et qu'elle ne donne acces qu'a la reservation qu'elle designe —
 * ni a la liste des ventes, ni au compte du voyageur.
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly BookingRepositoryContract $repository,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = CompanyScope::apply(
            $request->only('trip_id', 'status', 'channel', 'from', 'to', 'search', 'station_id', 'sort', 'direction'),
            $user,
        );

        // Un voyageur ne voit que ses propres reservations. La borne est posee
        // ici, sur le jeton, et non laissee a un parametre de requete.
        if ($user !== null && $user->hasRole('passenger')) {
            $filters['customer_user_id'] = $user->id;
            unset($filters['company_id']);
        }

        return BookingResource::collection(
            $this->repository->paginate($filters, $request->integer('per_page', 15)),
        );
    }

    /**
     * Reservations du voyageur connecte.
     *
     * Route distincte de `index` a dessein : `index` est bornee par une
     * permission de back-office, et l'ouvrir aux voyageurs obligerait a
     * reposer toute sa securite sur un `if` de role dans le controleur. Ici,
     * le filtre est le titulaire lui-meme — il n'existe aucun chemin vers les
     * reservations d'un autre.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        return BookingResource::collection($this->repository->paginate(
            [
                ...$request->only('status', 'sort', 'direction'),
                'customer_user_id' => $request->user()->id,
            ],
            $request->integer('per_page', 15),
        ));
    }

    /** Reservation en ligne : bloque les places et attend le paiement. */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->reserve($request->draft($request->user()?->id));

        return (new BookingResource($this->repository->findOrFail($booking->id)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        $this->authorizeAccess($request, $booking);

        return new BookingResource($this->repository->findOrFail($booking->id));
    }

    /**
     * Consultation par reference, sans authentification.
     *
     * La reference est normalisee avant recherche : elle est dictee au
     * telephone et retapee, espaces et minuscules compris. Refuser une
     * reference correcte pour une espace de trop ferait recommencer la saisie.
     */
    public function showByReference(string $reference): BookingResource
    {
        $booking = $this->repository->findByReference(Reference::normalize($reference));

        abort_if($booking === null, 404);

        return new BookingResource($booking);
    }

    /** Annulation d'une reservation ; les places retournent a la vente. */
    public function cancel(Request $request, Booking $booking): BookingResource
    {
        $this->authorizeAccess($request, $booking);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $cancelled = $this->bookings->cancel($booking, $validated['reason'] ?? null);

        return new BookingResource($this->repository->findOrFail($cancelled->id));
    }

    /**
     * Un voyageur accede a ses reservations, un exploitant a celles de sa
     * compagnie, l'administrateur a toutes.
     */
    private function authorizeAccess(Request $request, Booking $booking): void
    {
        $user = $request->user();

        if ($user !== null && $booking->customer_user_id === $user->id) {
            return;
        }

        if (! CompanyScope::allows($user, $booking->company_id)) {
            throw CompanyScopeViolationException::make();
        }
    }
}
