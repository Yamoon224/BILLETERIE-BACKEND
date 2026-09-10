<?php

namespace App\Domains\Notifications\Contracts;

use App\Domains\Notifications\DTOs\NotificationMessage;

/**
 * Envoi d'un message sortant (SMS, messagerie).
 *
 * Le contrat rend un booleen plutot que de lever : un SMS perdu est un
 * evenement ordinaire sur un reseau 2G intermittent, pas une exception. Le
 * faire echouer bruyamment ferait echouer la vente qui l'a declenche — alors
 * que le billet, lui, est bel et bien emis et reste consultable en ligne comme
 * reimprimable au guichet.
 */
interface NotificationSenderContract
{
    /** @return bool vrai si l'operateur a accepte le message */
    public function send(NotificationMessage $message): bool;
}
