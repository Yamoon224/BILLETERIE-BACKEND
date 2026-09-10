<?php

namespace App\Domains\Notifications\Senders;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;
use Illuminate\Support\Facades\Log;

/**
 * Pilote de developpement : ecrit le message dans les journaux au lieu de
 * l'envoyer.
 *
 * Le numero du destinataire est **tronque** avant journalisation. Les journaux
 * applicatifs sont lus, copies et parfois exportes vers un service tiers ; y
 * laisser des numeros de telephone complets de voyageurs en ferait un fichier
 * nominatif que personne n'a declare comme tel.
 */
final class LogNotificationSender implements NotificationSenderContract
{
    public function send(NotificationMessage $message): bool
    {
        Log::channel(config('logging.default'))->info('Notification sortante', [
            'channel' => $message->channel->value,
            'recipient' => $this->mask($message->recipient),
            'body' => $message->body,
            'media_url' => $message->mediaUrl,
        ]);

        return true;
    }

    private function mask(string $recipient): string
    {
        $length = strlen($recipient);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($recipient, 0, 4).str_repeat('*', $length - 6).substr($recipient, -2);
    }
}
