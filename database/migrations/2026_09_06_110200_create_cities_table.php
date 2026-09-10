<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Villes desservies.
 *
 * Referentiel partage par toutes les compagnies : deux compagnies qui
 * desservent Bouake doivent pointer la meme ville, sans quoi une recherche
 * voyageur « Abidjan -> Bouake » ne trouverait que la moitie des departs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');

            // Identifiant lisible dans les URL de recherche partagees
            // (« /recherche?depart=abidjan »). Un UUID dans un lien envoye par
            // messagerie ne se relit pas et ne se retape pas.
            $table->string('slug', 80)->unique();

            $table->string('region', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
