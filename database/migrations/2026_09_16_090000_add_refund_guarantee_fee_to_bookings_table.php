<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garantie remboursement optionnelle, chiffree separement du prix du trajet.
 *
 * Colonne distincte plutot qu'un `total_amount` muet sur sa composition :
 * un billet imprime, un rapprochement comptable ou un remboursement partiel
 * doivent pouvoir dire combien vient du trajet et combien vient de l'option,
 * sans recalculer a l'envers a partir d'une configuration qui peut avoir
 * change depuis. Par defaut a 0 : toute reservation existante ou creee sans
 * l'option garde un `total_amount` identique a avant cette migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedInteger('refund_guarantee_fee')->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('refund_guarantee_fee');
        });
    }
};
