<?php

/*
|--------------------------------------------------------------------------
| Billetterie
|--------------------------------------------------------------------------
|
| Reglages metier du domaine. Ils vivent ici, et non disperses dans les
| services, pour une raison simple : ce sont les seules valeurs qu'un
| exploitant peut vouloir changer sans toucher au code, et plusieurs d'entre
| elles sont archivees avec les decisions qu'elles ont produites.
|
*/

return [
    /*
     | Devise unique du lot. Le franc CFA n'a pas de sous-unite en circulation :
     | tous les montants sont donc des entiers de francs, jamais des flottants.
     | Voir App\Domains\Shared\Support\Money.
     */
    'currency' => env('TICKETING_CURRENCY', 'XOF'),

    /*
     | Duree du blocage de places d'une reservation en ligne non payee.
     |
     | C'est l'arbitrage le plus delicat du domaine : trop court, un voyageur
     | perd ses places pendant que son operateur mobile money valide le debit ;
     | trop long, un bus s'affiche complet alors qu'il ne l'est pas. Quinze
     | minutes couvrent un aller-retour USSD sur un reseau lent.
     */
    'hold_minutes' => (int) env('TICKETING_HOLD_MINUTES', 15),

    /*
     | Signature des billets.
     |
     | Le secret est distinct de APP_KEY a dessein : l'application agent doit
     | pouvoir verifier un QR code hors ligne, elle embarque donc cette cle.
     | Lui confier la cle de chiffrement de l'application entiere donnerait a
     | une tablette de gare les moyens de dechiffrer les sessions du serveur.
     */
    'signing' => [
        'key' => env('TICKETING_SIGNING_KEY', ''),

        /*
         | Version de cle portee par chaque signature emise. Une rotation
         | incremente ce numero ; les billets deja vendus restent verifiables
         | tant que leur version figure dans `accepted_versions`. Sans ce
         | numero, une rotation invaliderait d'un coup tous les billets en
         | circulation — y compris ceux d'un bus en cours d'embarquement.
         */
        'version' => (int) env('TICKETING_KEY_VERSION', 1),

        'accepted_versions' => array_map(
            'intval',
            array_filter(explode(',', (string) env(
                'TICKETING_ACCEPTED_KEY_VERSIONS',
                (string) env('TICKETING_KEY_VERSION', 1),
            ))),
        ),

        /*
         | Cles retirees du service mais encore acceptees en verification,
         | indexees par numero de version : {"1": "base64:..."}.
         */
        'previous_keys' => (array) (json_decode((string) env('TICKETING_PREVIOUS_KEYS', '{}'), true) ?: []),
    ],

    /*
     | Commission plateforme par defaut, en pour mille du montant encaisse.
     | Chaque compagnie peut porter son propre taux ; celui-ci s'applique a
     | defaut. En pour mille et non en pourcentage : 2,5 % ne s'ecrit pas en
     | entier, et un flottant sur une assiette financiere finit toujours par
     | produire un franc introuvable au rapprochement.
     */
    'commission_per_mille' => (int) env('TICKETING_COMMISSION_PER_MILLE', 25),

    /*
     | Fenetre d'embarquement : intervalle, autour de l'heure de depart, pendant
     | lequel un billet peut etre scanne. Hors fenetre, le scan est refuse avec
     | un motif explicite plutot qu'accepte « au cas ou » — un billet de la
     | veille presente au mauvais bus doit se voir.
     */
    'boarding_window' => [
        'opens_minutes_before' => (int) env('TICKETING_BOARDING_OPENS_BEFORE', 120),
        'closes_minutes_after' => (int) env('TICKETING_BOARDING_CLOSES_AFTER', 30),
    ],

    /*
     | Age maximal accepte pour une vente hors ligne remontee par un agent.
     | Au-dela, la vente est refusee et remonte en anomalie : une vente d'il y a
     | trois jours qui arrive apres le depart du bus n'est plus une vente, c'est
     | un incident a traiter humainement.
     */
    'offline_sync_max_age_hours' => (int) env('TICKETING_OFFLINE_MAX_AGE_HOURS', 48),

    /*
     | Longueur du suffixe aleatoire des references publiques (reservation,
     | billet). Assez court pour se dicter au telephone, assez long pour qu'une
     | reference ne se devine pas a partir d'une autre.
     */
    'reference_random_length' => 6,
];
