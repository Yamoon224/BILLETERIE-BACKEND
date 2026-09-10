<?php

namespace App\Domains\Notifications\DTOs;

use App\Domains\Notifications\Enums\NotificationChannel;

/**
 * Un message sortant, pret a partir.
 *
 * Le corps est deja rendu : le pilote d'envoi ne connait ni modele ni
 * variables, il transporte du texte. C'est ce qui permet de changer
 * d'operateur sans rien savoir du contenu des billets.
 */
final readonly class NotificationMessage
{
    public function __construct(
        public NotificationChannel $channel,
        /** Numero au format international, sans espaces. */
        public string $recipient,
        public string $body,
        /** Lien vers le billet, pour les canaux qui savent afficher une image. */
        public ?string $mediaUrl = null,
    ) {}
}
