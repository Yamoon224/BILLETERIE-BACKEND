<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compagnies de transport partenaires.
 *
 * C'est l'entite de cloisonnement du systeme : un gestionnaire ne voit que les
 * departs, les ventes et les recettes de sa compagnie. Toutes les tables
 * d'exploitation portent donc `company_id`, y compris quand il serait
 * derivable par jointure — un filtre de securite qui depend d'une jointure a
 * trois niveaux finit toujours par etre oublie quelque part.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();

            /*
             * Commission plateforme propre a la compagnie, en pour mille.
             * `null` signifie « appliquer le taux par defaut de la
             * plateforme » et non « zero » : les deux se confondraient dans un
             * entier par defaut, et une compagnie exoneree par erreur ne se
             * verrait jamais.
             */
            $table->unsignedSmallInteger('commission_per_mille')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
