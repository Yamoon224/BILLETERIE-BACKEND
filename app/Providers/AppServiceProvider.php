<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Jeton a cle UUID, aligne sur le reste du schema : l'identifiant du
        // jeton voyage en clair dans l'en-tete Authorization, un entier
        // sequentiel y annoncerait combien de jetons ont ete emis.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        /*
         * Les relations doivent etre chargees explicitement.
         *
         * Sans cela, un acces a une relation non chargee declenche une requete
         * silencieuse — par ligne. Sur un ecran de recherche qui affiche vingt
         * departs avec leur itineraire, leur vehicule et leur gare, cela fait
         * soixante requetes invisibles, et le probleme ne se voit qu'en
         * production sur un reseau mobile.
         *
         * Actif hors production uniquement : en production, une relation
         * oubliee doit degrader la performance, pas casser la page d'un
         * voyageur en train de payer.
         */
        Model::preventLazyLoading(! $this->app->isProduction());

        // Une ecriture sur un attribut absent de `$fillable` est une erreur de
        // developpement, pas une donnee a ignorer en silence.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
