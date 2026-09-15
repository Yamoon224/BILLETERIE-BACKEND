<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement d'un compte a un partenaire (appartements, location auto).
 *
 * Symetrique de `company_id` mais independant : un compte peut relever d'une
 * compagnie de transport, d'un partenaire, des deux (rare) ou d'aucun des deux
 * (voyageur, administrateur plateforme). Restreint et non « cascade », pour la
 * meme raison que `company_id` : supprimer un partenaire ne doit pas faire
 * disparaitre les comptes qui geraient ses fiches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('partner_id')
                ->nullable()
                ->after('company_id')
                ->constrained('partners')
                ->restrictOnDelete();

            $table->index(['partner_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['partner_id']);
            $table->dropIndex(['partner_id', 'is_active']);
            $table->dropColumn('partner_id');
        });
    }
};
