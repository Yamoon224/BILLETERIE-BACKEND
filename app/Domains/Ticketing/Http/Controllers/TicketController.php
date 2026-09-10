<?php

namespace App\Domains\Ticketing\Http\Controllers;

use App\Domains\Shared\Exceptions\CompanyScopeViolationException;
use App\Domains\Shared\Support\CompanyScope;
use App\Domains\Shared\Support\Reference;
use App\Domains\Ticketing\Contracts\TicketRepositoryContract;
use App\Domains\Ticketing\Http\Resources\TicketResource;
use App\Domains\Ticketing\Services\TicketIssuanceService;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Consultation des billets et rendu de leur QR code.
 */
class TicketController extends Controller
{
    public function __construct(
        private readonly TicketRepositoryContract $tickets,
        private readonly TicketIssuanceService $issuance,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return TicketResource::collection($this->tickets->paginate(
            CompanyScope::apply(
                $request->only('trip_id', 'booking_id', 'status', 'search', 'sort', 'direction'),
                $request->user(),
            ),
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Liste d'embarquement d'un depart.
     *
     * Chargee en une fois par l'application agent avant de perdre le reseau :
     * c'est elle qui lui permet de reconnaitre les billets d'un bus donne, et
     * de refuser un second scan meme hors ligne.
     */
    public function forTrip(Request $request, string $tripId): AnonymousResourceCollection
    {
        return TicketResource::collection($this->tickets->forTrip($tripId));
    }

    /**
     * QR code d'un billet, en SVG.
     *
     * Publique par reference de billet : le voyageur qui a achete sans compte
     * ouvre le lien recu par SMS. Le code est imprevisible et ne donne acces
     * qu'a ce billet.
     */
    public function qrCode(string $code): Response
    {
        $ticket = $this->tickets->findByCode(Reference::normalize($code));

        abort_if($ticket === null, 404);

        return response($this->issuance->qrSvg($ticket), 200, [
            'Content-Type' => 'image/svg+xml',
            // Le QR d'un billet emis ne change jamais : le mettre en cache
            // evite de le recalculer a chaque affichage sur un reseau lent.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * QR code matriciel, pour l'imprimante thermique Bluetooth du guichet.
     *
     * Reserve aux agents : c'est un flux d'impression, pas une page a partager.
     */
    public function printableQrCode(Request $request, Ticket $ticket): Response
    {
        $ticket->loadMissing('trip');

        if (! CompanyScope::allows($request->user(), $ticket->trip->company_id)) {
            throw CompanyScopeViolationException::make();
        }

        return response($this->issuance->qrPng($ticket), 200, [
            'Content-Type' => 'image/png',
        ]);
    }
}
