<?php

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
|
| Le frontend Next.js et l'application agent vivent sur d'autres origines que
| l'API. Les origines autorisees sont lues dans l'environnement plutot
| qu'ecrites en dur : la recette et la production n'ont pas la meme URL front
| que le poste de developpement, et `*` serait inacceptable pour une API qui
| expose les recettes des compagnies et des donnees nominatives de voyageurs.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:3000'))),
)));

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Le nom de fichier propose par le serveur doit rester lisible par le
    // navigateur, sinon les exports du tableau de bord arrivent tous sous le
    // meme « download.csv ».
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    // Authentification par jeton Bearer, pas par cookie de session : aucune
    // raison d'autoriser l'envoi de credentials cross-origin.
    'supports_credentials' => false,
];
