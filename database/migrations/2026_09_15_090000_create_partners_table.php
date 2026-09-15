<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proprietaires et agences partenaires (appartements, location de vehicules).
 *
 * Distinct des `companies` (compagnies de transport) : un loueur d'appartement
 * ou une agence de location automobile n'exploite ni gare ni depart, et le
 * confondre avec une compagnie de bus melangerait deux referentiels regis par
 * des regles metier differentes. `type` indique le ou les catalogues que ce
 * partenaire exploite ; il ne contraint rien en base, il oriente l'ecran de
 * gestion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type', 20)->default('both');

            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email')->nullable();

            $table->foreignUuid('city_id')->nullable()->constrained('cities')->nullOnDelete();

            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
