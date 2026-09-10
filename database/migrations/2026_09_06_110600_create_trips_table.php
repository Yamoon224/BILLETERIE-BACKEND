<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departs : l'exemplaire vendable d'un itineraire, a une date et une heure.
 *
 * Trois colonnes sont des **copies figees** prises a la creation du depart —
 * `price`, `seat_capacity`, `seats_per_row`. Ce n'est pas de la
 * denormalisation de confort : un tarif d'itineraire revu a la hausse ne doit
 * pas reecrire le prix des billets deja vendus, et un changement de bus ne
 * doit pas renumeroter des places deja attribuees. Ce que le voyageur a
 * achete doit rester lisible tel qu'il l'a achete.
 *
 * Le nombre de places vendues n'est **pas** stocke : il se compte depuis
 * `tickets`. Un compteur denormalise sur une table qui recoit des ventes
 * concurrentes au guichet et en ligne derive au premier incident, et un
 * compteur faux sur un plan de salle se paie en voyageurs debout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Reference lisible, dictable au telephone : « DEP-2026-0412-A7X ».
            $table->string('reference', 24)->unique();

            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('itinerary_id')->constrained('itineraries')->restrictOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignUuid('departure_station_id')->constrained('stations')->restrictOnDelete();
            $table->foreignUuid('arrival_station_id')->constrained('stations')->restrictOnDelete();

            $table->dateTime('departs_at');
            $table->dateTime('arrives_at')->nullable();

            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('seat_capacity');
            $table->unsignedTinyInteger('seats_per_row');

            $table->string('status', 20)->default('scheduled');
            $table->string('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            // La recherche voyageur interroge toujours « quels departs, ce
            // jour-la, encore ouverts a la vente » : l'index suit cet ordre.
            $table->index(['departs_at', 'status']);
            $table->index(['company_id', 'departs_at']);
            $table->index(['itinerary_id', 'departs_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
