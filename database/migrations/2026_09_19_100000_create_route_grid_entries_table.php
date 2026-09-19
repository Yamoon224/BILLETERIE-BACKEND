<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grille des trajets : les informations affichees au voyageur pour une liaison
 * qui n'est pas encore reservable en ligne.
 *
 * Volontairement separee de `itineraries` : un itineraire appartient a une
 * compagnie et porte des departs vendables, alors qu'une ligne de grille est
 * une simple vitrine editee par l'administrateur de la plateforme (« voici ce
 * que coute et dure ce trajet, il arrive bientot »). Reutiliser les
 * itineraires obligerait a creer une compagnie et des departs fictifs pour
 * montrer un prix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_grid_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('origin_city_id')->constrained('cities')->cascadeOnDelete();
            $table->foreignUuid('destination_city_id')->constrained('cities')->cascadeOnDelete();

            // Compagnie a titre informatif : simple texte, sans lien vers
            // `companies` — elle n'est pas (encore) partenaire de la plateforme.
            $table->string('company_name', 120)->nullable();

            // Prix indicatif, en francs CFA entiers (voir itineraries.base_price).
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('distance_km')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            // Horaires habituels, liste de « HH:MM ». Du JSON plutot qu'une
            // table fille : ils n'ont aucune vie propre, on les lit et on les
            // ecrit toujours avec leur ligne.
            $table->json('departure_times')->nullable();
            $table->string('notes', 255)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['origin_city_id', 'destination_city_id', 'is_active'], 'route_grid_od_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_grid_entries');
    }
};
