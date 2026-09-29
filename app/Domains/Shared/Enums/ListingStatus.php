<?php

namespace App\Domains\Shared\Enums;

/**
 * Etat de validation d'une fiche soumise a l'administrateur de plateforme.
 *
 * Commun aux compagnies, appartements et vehicules de location : dans les
 * trois cas, la meme question se pose avant qu'une fiche n'apparaisse au
 * voyageur — l'administrateur l'a-t-il verifiee ? Une seule enumeration plutot
 * que trois identiques, pour la meme raison que ResourceInUseException est
 * unique : la sixieme copie aurait fini par diverger.
 */
enum ListingStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Active => 'Active',
            self::Rejected => 'Rejetee',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
