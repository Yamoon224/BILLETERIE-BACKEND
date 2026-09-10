<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Taches planifiees
|--------------------------------------------------------------------------
|
| Une seule tache, mais elle porte une garantie du domaine : les places d'une
| reservation non payee doivent retourner a la vente une fois le delai ecoule.
|
*/

/*
 * Chaque minute, et sans chevauchement.
 *
 * La frequence n'est pas un exces de zele : chaque minute d'attente est une
 * minute pendant laquelle un bus s'affiche complet alors qu'il ne l'est pas.
 * `withoutOverlapping` evite qu'une execution lente — au retour d'une
 * interruption de service, l'arriere peut etre important — soit doublee par la
 * suivante, ce qui ferait traiter deux fois les memes reservations.
 */
Schedule::command('bookings:expire')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
