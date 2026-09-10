<?php

namespace App\Domains\Audit\Http\Resources;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\City;
use App\Models\Company;
use App\Models\Itinerary;
use App\Models\Payment;
use App\Models\Station;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une entree du journal, rendue lisible.
 *
 * Le type d'objet est traduit en libelle metier : un journal qui affiche
 * `App\Models\Booking` fuite la structure interne de l'application et ne
 * signifie rien pour l'exploitant qui le consulte.
 *
 * @mixin ActivityLog
 */
class AuditLogResource extends JsonResource
{
    /** @var array<class-string, string> */
    private const SUBJECT_LABELS = [
        Booking::class => 'Reservation',
        Ticket::class => 'Billet',
        Payment::class => 'Encaissement',
        Trip::class => 'Depart',
        Itinerary::class => 'Itineraire',
        Vehicle::class => 'Vehicule',
        Station::class => 'Gare',
        City::class => 'Ville',
        Company::class => 'Compagnie',
        User::class => 'Utilisateur',
    ];

    /** @var array<string, string> */
    private const EVENT_LABELS = [
        'created' => 'Creation',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'event_label' => self::EVENT_LABELS[$this->event] ?? ($this->event ?? 'Evenement'),
            'subject_type' => $this->subject_type,
            'subject_label' => self::SUBJECT_LABELS[$this->subject_type] ?? 'Objet',
            'subject_id' => $this->subject_id,
            'causer' => $this->whenLoaded('causer', fn () => $this->causer instanceof User ? [
                'id' => $this->causer->id,
                'name' => $this->causer->name,
            ] : null),
            // Une action sans auteur vient du systeme (expiration d'un
            // blocage, rappel d'un agregateur) : le dire explicitement evite
            // qu'on la lise comme une donnee manquante. Seul un compte
            // utilisateur peut etre auteur ici ; tout autre type est traite
            // comme le systeme plutot que de supposer ses attributs.
            'causer_label' => $this->causer instanceof User ? $this->causer->name : 'Systeme',
            'properties' => $this->properties,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
