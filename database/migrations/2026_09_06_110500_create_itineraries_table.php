<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itineraires : une liaison ville a ville exploitee par une compagnie.
 *
 * L'itineraire porte le tarif de reference et la duree ; le depart date
 * (`trips`) porte l'exemplaire vendable. Separer les deux permet de publier
 * cinquante departs d'« Abidjan -> Yamoussoukro » sans recopier cinquante fois
 * la meme description de liaison, et de changer un tarif de reference sans
 * toucher aux departs deja mis en vente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itineraries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('origin_city_id')->constrained('cities')->restrictOnDelete();
            $table->foreignUuid('destination_city_id')->constrained('cities')->restrictOnDelete();

            $table->unsignedSmallInteger('distance_km')->nullable();
            $table->unsignedSmallInteger('duration_minutes');

            /*
             * Tarif de reference, en francs CFA entiers. Le XOF n'a pas de
             * sous-unite en circulation : un decimal ici inviterait a des
             * arrondis qui n'existent pas au guichet.
             */
            $table->unsignedInteger('base_price');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Une compagnie n'exploite qu'une liaison par couple de villes :
            // deux itineraires identiques produiraient deux tarifs de
            // reference concurrents pour le meme trajet.
            $table->unique(['company_id', 'origin_city_id', 'destination_city_id'], 'itineraries_company_od_unique');

            $table->index(['origin_city_id', 'destination_city_id', 'is_active'], 'itineraries_od_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itineraries');
    }
};
