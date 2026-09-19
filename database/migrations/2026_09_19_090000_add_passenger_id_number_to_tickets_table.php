<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numero de piece d'identite du voyageur, saisi a la reservation.
 *
 * Porte par le billet et non par la reservation : une reservation peut couvrir
 * plusieurs voyageurs, et c'est a l'embarquement, place par place, qu'un agent
 * confronte la piece au passager. Nullable, car le champ est facultatif : ne
 * pas l'exiger evite qu'un voyageur presse abandonne le tunnel pour un numero
 * que personne ne verifie a la vente.
 *
 * Donnee personnelle : elle n'est exposee par aucune ressource de l'API
 * publique (voir `TicketResource`), le lien de reservation envoye par SMS
 * suffisant a lire un billet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('passenger_id_number', 30)->nullable()->after('passenger_phone');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('passenger_id_number');
        });
    }
};
