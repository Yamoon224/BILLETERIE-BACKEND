<?php

namespace App\Domains\Notifications\Enums;

/**
 * Etat d'un envoi sortant.
 *
 * L'echec est un etat normal, pas une exception : sur un reseau 2G
 * intermittent, une partie des SMS n'arrive pas du premier coup. Il est
 * conserve et rejouable, et n'annule jamais la vente — le billet reste
 * consultable en ligne et reimprimable au guichet.
 */
enum NotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function isRetryable(): bool
    {
        return $this === self::Failed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En file',
            self::Sent => 'Envoye',
            self::Failed => 'Echoue',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
