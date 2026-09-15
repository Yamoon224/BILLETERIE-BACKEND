<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicules proposes a la location courte duree (aeroport, ville).
 *
 * Sans rapport avec `vehicles` (le parc de bus, avec son plan de salle) : une
 * citadine louee a la journee n'a ni siege numerote ni itineraire, et partage
 * la table du parc de bus aurait force des colonnes vides des deux cotes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_vehicles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignUuid('city_id')->constrained('cities')->restrictOnDelete();

            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('year')->nullable();

            $table->string('category', 20)->default('citadine');
            $table->string('transmission', 20)->default('manual');
            $table->string('fuel_type', 20)->default('petrol');

            $table->unsignedTinyInteger('seats')->default(5);
            $table->unsignedInteger('price_per_day');

            // Chauffeur en option : frequent en Cote d'Ivoire pour un
            // visiteur qui ne connait pas la ville, rare pour un habitue.
            $table->boolean('with_driver_available')->default(false);

            // Nullable : une fiche catalogue peut exister avant l'affectation
            // d'un vehicule immatricule precis (plusieurs unites du meme
            // modele chez une agence).
            $table->string('plate_number', 20)->nullable()->unique();

            $table->string('cover_photo_url')->nullable();
            $table->json('photo_urls')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['partner_id', 'is_active']);
            $table->index(['city_id', 'is_active']);
            $table->index('category');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_vehicles');
    }
};
