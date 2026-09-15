<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appartements meubles proposes a la location courte duree.
 *
 * `city_id` est restreint et non nul : un appartement sans ville n'est pas
 * cherchable, et une ville encore citee par un appartement ne peut pas etre
 * supprimee par erreur — elle se desactive plutot.
 *
 * Aucune reservation en ligne pour ce catalogue a ce stade (voir README) : ni
 * calendrier de disponibilite ni table de reservation associee. `is_active`
 * regit a lui seul la visibilite dans la recherche publique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apartments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignUuid('city_id')->constrained('cities')->restrictOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Quartier : plus precis que la ville, moins rigide qu'une gare —
            // « Assinie Plage », « Grand-Bassam France »… Champ libre, pas de
            // referentiel dedie, le volume ne le justifie pas encore.
            $table->string('neighborhood', 120)->nullable();
            $table->string('address_line')->nullable();

            $table->unsignedTinyInteger('bedrooms')->default(1);
            $table->unsignedTinyInteger('bathrooms')->default(1);
            $table->unsignedTinyInteger('capacity')->default(2);

            $table->unsignedInteger('price_per_night');

            // Liste libre validee contre App\Domains\Housing\Enums\ApartmentAmenity
            // a la saisie : un JSON evite une table de jointure pour une donnee
            // qui ne sert qu'a l'affichage, jamais a une jointure ou un calcul.
            $table->json('amenities')->nullable();

            $table->string('cover_photo_url')->nullable();
            $table->json('photo_urls')->nullable();

            // Mise en avant manuelle dans « Quartiers prises » / « Les
            // appartements » : un simple drapeau plutot qu'un score calcule,
            // parce que personne ne sait aujourd'hui sur quoi baser ce score.
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['partner_id', 'is_active']);
            $table->index(['city_id', 'is_active']);
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apartments');
    }
};
