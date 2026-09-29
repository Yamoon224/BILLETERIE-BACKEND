<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gare d'affectation d'un agent de guichet.
 *
 * Nul pour tout compte qui ne tient pas un guichet physique : un gestionnaire
 * ou un administrateur ne sont affectes a aucune gare en particulier.
 * `nullOnDelete` plutot que `restrict` : une gare fermee ne doit pas empecher
 * sa suppression parce qu'un ancien agent y est encore rattache en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('station_id')
                ->nullable()
                ->after('company_id')
                ->constrained('stations')
                ->nullOnDelete();

            $table->index(['station_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['station_id']);
            $table->dropIndex(['station_id', 'is_active']);
            $table->dropColumn('station_id');
        });
    }
};
