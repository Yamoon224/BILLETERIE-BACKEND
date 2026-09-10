<?php

use App\Domains\Payments\Gateways\SimulatedMobileMoneyGateway;

/*
|--------------------------------------------------------------------------
| Paiement
|--------------------------------------------------------------------------
|
| Le cahier des charges impose la delegation a un agregateur mobile money
| tiers plutot que des connexions separees par operateur, et interdit le
| stockage de toute donnee de paiement en propre. Les deux contraintes sont
| tenues par PaymentGatewayContract : l'application ne connait qu'une
| intention de paiement et une reference externe.
|
*/

return [
    /*
     | Pilote actif. `simulated` rejoue le cycle complet (en attente -> succes
     | ou echec) sans appel reseau : c'est celui du poste de developpement et
     | de la suite de tests. Brancher un agregateur reel consiste a ajouter une
     | classe ici, pas a toucher au domaine Booking.
     */
    'gateway' => env('PAYMENT_GATEWAY', 'simulated'),

    'gateways' => [
        'simulated' => [
            'driver' => SimulatedMobileMoneyGateway::class,
        ],
    ],

    'base_url' => env('PAYMENT_GATEWAY_BASE_URL', ''),
    'api_key' => env('PAYMENT_GATEWAY_API_KEY', ''),

    /*
     | Secret de verification des rappels de l'agregateur. Un webhook non signe
     | est une API d'encaissement ouverte a tous : la verification n'est pas
     | optionnelle, elle est la seule chose qui distingue un rappel de
     | l'operateur d'un POST forge.
     */
    'webhook_secret' => env('PAYMENT_GATEWAY_WEBHOOK_SECRET', ''),

    /*
     | Delai au-dela duquel une intention de paiement restee « en attente » est
     | consideree perdue. Aligne par defaut sur la duree de blocage des places :
     | garder l'intention vivante apres la liberation des places produirait un
     | paiement accepte pour un siege deja revendu.
     */
    'intent_timeout_minutes' => (int) env('PAYMENT_INTENT_TIMEOUT_MINUTES', 15),
];
