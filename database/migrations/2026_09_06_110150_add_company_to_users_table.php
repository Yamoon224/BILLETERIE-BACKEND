<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement d'un compte a une compagnie.
 *
 * Separee de la creation de `users` parce que la contrainte pointe vers une
 * table qui n'existait pas encore : l'ordre des migrations est ici une
 * information, pas une contrainte contournee.
 *
 * `company_id` est nul pour les voyageurs et les administrateurs de la
 * plateforme — deux populations qui n'appartiennent a aucune compagnie, pour
 * des raisons opposees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('company_id')
                ->nullable()
                ->after('phone')
                // Restreint et non « cascade » : supprimer une compagnie ne
                // doit pas faire disparaitre les comptes qui ont encaisse pour
                // elle. Le refus force a traiter le cas explicitement.
                ->constrained('companies')
                ->restrictOnDelete();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'is_active']);
            $table->dropColumn('company_id');
        });
    }
};
