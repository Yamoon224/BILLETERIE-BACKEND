<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicules d'une compagnie.
 *
 * La capacite et le plan de salle vivent ici, mais sont **recopies** dans
 * chaque depart au moment de sa creation (voir `trips`). Changer de bus la
 * veille d'un depart ne doit pas reecrire la numerotation des places deja
 * vendues sur les departs passes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();

            // Immatriculation : unique a l'echelle nationale, donc a l'echelle
            // de la plateforme — deux compagnies ne peuvent pas exploiter le
            // meme vehicule.
            $table->string('registration', 20)->unique();

            $table->string('model')->nullable();
            $table->string('class', 20)->default('standard');

            $table->unsignedSmallInteger('seat_capacity');

            /*
             * Places par rangee, plan de salle compris : 4 pour un 2+2 (le cas
             * courant), 5 pour un 3+2. Le numero de place (« 12A ») se deduit
             * de ce seul entier, ce qui evite de stocker un plan de salle
             * complet en JSON pour une information qui tient dans un chiffre.
             */
            $table->unsignedTinyInteger('seats_per_row')->default(4);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
