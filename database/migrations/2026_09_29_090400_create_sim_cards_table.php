<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cartes SIM du SMS Box qui porte l'envoi des billets electroniques.
 *
 * Une ligne par operateur : le SMS Box bascule d'une carte a l'autre selon
 * l'operateur du destinataire, et chacune a son propre solde a surveiller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sim_cards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('operator', 20)->unique();
            $table->string('phone_number', 20)->nullable();
            $table->unsignedInteger('balance')->default(0);

            // En dessous de ce seuil, la carte est signalee « credit bas » a
            // l'administrateur avant qu'elle ne tombe a sec en plein envoi.
            $table->unsignedInteger('low_balance_threshold')->default(1000);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_cards');
    }
};
