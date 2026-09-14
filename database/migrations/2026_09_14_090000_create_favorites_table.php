<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trajets favoris : un couple ville de depart / ville d'arrivee qu'un
 * voyageur enregistre pour le retrouver en un geste, sans retaper les deux
 * villes a chaque recherche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('origin_city_id')->constrained('cities')->cascadeOnDelete();
            $table->foreignUuid('destination_city_id')->constrained('cities')->cascadeOnDelete();
            $table->timestamps();

            // Un voyageur ne favorise qu'une fois le meme trajet : ajouter deux
            // fois « Abidjan -> Bouake » ne doublerait rien d'utile et ferait
            // deux lignes a supprimer pour n'en retirer qu'une.
            $table->unique(['user_id', 'origin_city_id', 'destination_city_id'], 'favorites_user_od_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
