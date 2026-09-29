<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bannieres et tuiles « A la une » de la page d'accueil.
 *
 * `zone` distingue l'unique banniere hero des tuiles « a la une » ; `kind`
 * distingue, parmi les tuiles, le contenu editorial Kaara de la publicite
 * partenaire payante — deux badges d'affichage differents dans le mockup, et
 * deux realites commerciales differentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('zone', 20);
            $table->string('kind', 20)->default('editorial');

            // Renseigne pour une publicite partenaire : le nom affiche a
            // l'administrateur dans la liste, distinct du partenaire lie.
            $table->string('advertiser_name')->nullable();
            $table->foreignUuid('partner_id')->nullable()->constrained('partners')->nullOnDelete();

            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['zone', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
