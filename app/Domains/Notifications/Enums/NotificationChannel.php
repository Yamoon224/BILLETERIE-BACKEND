<?php

namespace App\Domains\Notifications\Enums;

/**
 * Canal d'envoi du billet electronique.
 *
 * Le SMS reste le canal par defaut : il ne demande ni forfait data ni
 * application installee, ce qui est la condition pour qu'un billet arrive sur
 * un telephone en zone 2G. La messagerie instantanee vient en complement quand
 * le voyageur l'a fournie — elle porte le QR code en image, la ou le SMS ne
 * transporte qu'un lien et un code.
 */
enum NotificationChannel: string
{
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';

    /** Ce canal peut-il transporter une image (le QR code lui-meme) ? */
    public function carriesImages(): bool
    {
        return $this === self::Whatsapp;
    }

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::Whatsapp => 'WhatsApp',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
