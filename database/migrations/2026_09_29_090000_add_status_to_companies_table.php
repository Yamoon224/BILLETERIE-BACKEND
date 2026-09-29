<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statut de validation d'une compagnie par l'administrateur de plateforme.
 *
 * `active` par defaut : les compagnies existantes exploitent deja des
 * lignes, les traiter en attente de validation les ferait disparaitre des
 * ecrans qui filtrent sur le statut. Seules les nouvelles compagnies
 * onboardees en `pending` passent reellement par le circuit de validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('is_active');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
