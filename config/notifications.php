<?php

use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Domains\Notifications\Senders\LogNotificationSender;

/*
|--------------------------------------------------------------------------
| Notifications sortantes
|--------------------------------------------------------------------------
|
| Le billet electronique part par SMS ou messagerie instantanee. Le choix de
| l'operateur n'appartient pas au domaine : celui-ci ne connait que
| NotificationSenderContract.
|
*/

return [
    'driver' => env('NOTIFICATION_DRIVER', 'log'),

    'drivers' => [
        // Ecrit dans les journaux applicatifs : pilote du poste de
        // developpement, ou aucun SMS ne doit partir vers un vrai numero.
        'log' => LogNotificationSender::class,
        // Conserve les envois en memoire : pilote de la suite de tests.
        'array' => ArrayNotificationSender::class,
    ],

    'sms' => [
        'sender' => env('NOTIFICATION_SMS_SENDER', 'BILLET'),
    ],

    /*
     | Nombre de tentatives d'envoi avant abandon. Un SMS perdu sur un reseau
     | 2G intermittent ne doit pas faire echouer la vente : l'envoi est
     | journalise et rejouable, et la reservation reste valide sans lui.
     */
    'max_attempts' => (int) env('NOTIFICATION_MAX_ATTEMPTS', 3),
];
