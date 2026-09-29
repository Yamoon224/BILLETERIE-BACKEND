<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carte SIM ayant porte l'envoi, quand le canal est le SMS.
 *
 * Nul pour les envois anterieurs a l'introduction du SMS Box et pour les
 * envois WhatsApp, qui ne transitent par aucune carte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->foreignUuid('sim_card_id')
                ->nullable()
                ->after('channel')
                ->constrained('sim_cards')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->dropForeign(['sim_card_id']);
            $table->dropColumn('sim_card_id');
        });
    }
};
