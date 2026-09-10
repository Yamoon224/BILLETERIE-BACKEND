<?php

namespace App\Domains\Notifications\Senders;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;

/**
 * Pilote de test : conserve les messages en memoire.
 *
 * Enregistre comme singleton (voir DomainServiceProvider), sans quoi le test
 * inspecterait une instance differente de celle qui a servi a l'envoi.
 */
final class ArrayNotificationSender implements NotificationSenderContract
{
    /** @var list<NotificationMessage> */
    private array $sent = [];

    public function send(NotificationMessage $message): bool
    {
        $this->sent[] = $message;

        return true;
    }

    /** @return list<NotificationMessage> */
    public function sent(): array
    {
        return $this->sent;
    }

    public function flush(): void
    {
        $this->sent = [];
    }
}
