<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gares routieres et points d'embarquement.
 *
 * `company_id` nullable : une gare peut appartenir a une compagnie (son propre
 * terminal) ou etre partagee entre plusieurs (une gare routiere municipale).
 * Les deux existent en pratique, et forcer l'une des deux formes obligerait a
 * dupliquer la meme gare autant de fois qu'elle a d'occupants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('city_id')->constrained('cities')->restrictOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();

            $table->string('name');
            $table->string('address')->nullable();

            // Coordonnees facultatives : la precision au dix-millionieme de
            // degre (environ un centimetre) est celle des GPS grand public.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['city_id', 'is_active']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
