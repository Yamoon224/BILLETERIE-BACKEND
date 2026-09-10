<?php

namespace App\Domains\Ticketing\Enums;

/**
 * Verdict d'un scan a l'embarquement.
 *
 * Un seul verdict laisse monter (`accepted`) ; tous les autres sont des refus
 * motives. Le motif compte autant que le refus : devant la porte d'un bus,
 * « billet deja utilise » appelle un controle d'identite, « mauvais depart »
 * appelle une reorientation, et « signature invalide » appelle un responsable.
 * Un simple booleen obligerait l'agent a deviner lequel des trois.
 */
enum ScanOutcome: string
{
    case Accepted = 'accepted';
    case AlreadyUsed = 'already_used';
    case WrongTrip = 'wrong_trip';
    case NotFound = 'not_found';
    case InvalidSignature = 'invalid_signature';
    case Cancelled = 'cancelled';
    case TripCancelled = 'trip_cancelled';
    case OutsideBoardingWindow = 'outside_boarding_window';

    public function isAccepted(): bool
    {
        return $this === self::Accepted;
    }

    /**
     * Ce verdict merite-t-il une alerte, par opposition a une simple erreur de
     * manipulation ? Une signature invalide ou un billet deja utilise sont les
     * deux signatures d'une fraude ; se tromper de bus n'en est pas une.
     */
    public function isSuspicious(): bool
    {
        return in_array($this, [self::AlreadyUsed, self::InvalidSignature], true);
    }

    /** Code HTTP a rendre pour ce verdict. */
    public function httpStatus(): int
    {
        return match ($this) {
            self::Accepted => 200,
            self::NotFound => 404,
            self::InvalidSignature => 422,
            default => 409,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Embarquement autorise',
            self::AlreadyUsed => 'Billet deja utilise',
            self::WrongTrip => 'Billet emis pour un autre depart',
            self::NotFound => 'Billet introuvable',
            self::InvalidSignature => 'Signature du billet invalide',
            self::Cancelled => 'Billet annule',
            self::TripCancelled => 'Depart annule',
            self::OutsideBoardingWindow => 'Hors de la fenetre d embarquement',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
