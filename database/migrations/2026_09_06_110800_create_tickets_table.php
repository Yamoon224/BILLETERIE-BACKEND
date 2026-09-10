<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billets : une place, un voyageur, un QR code.
 *
 * Deux garanties du cahier des charges reposent sur cette table, et toutes
 * deux sont tenues par la base plutot que par du code applicatif :
 *
 * 1. **Une place n'est jamais vendue deux fois** — index unique
 *    `(trip_id, seat_number)`. Le guichet et le site vendent en parallele,
 *    parfois a la seconde pres : une verification applicative « la place
 *    est-elle libre ? » suivie d'une insertion laisse toujours une fenetre
 *    entre les deux. L'index, lui, n'en laisse aucune.
 *
 *    Un billet annule doit rendre sa place a la vente. Plutot que de
 *    conditionner l'unicite au statut — ce qu'un index MySQL ne sait pas
 *    faire —, l'annulation **vide** `seat_number` et recopie la valeur dans
 *    `released_seat_number`. Les NULL ne se heurtent pas dans un index unique :
 *    la place redevient libre, et l'on garde la trace de laquelle.
 *
 * 2. **Un billet scanne une fois est refuse au second scan** — `scanned_at`
 *    porte l'etat, et la validation le pose sous verrou de ligne. Le refus est
 *    donc decide sur la donnee verrouillee, pas sur une lecture prealable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();

            // Recopie depuis la reservation : le controle a l'embarquement
            // interroge par depart, jamais par reservation.
            $table->foreignUuid('trip_id')->constrained('trips')->restrictOnDelete();

            // Code imprime sous le QR : il permet une saisie manuelle quand le
            // QR est abime ou que la camera de la tablette refuse de lire.
            $table->string('code', 24)->unique();

            /*
             * Place attribuee. Nullable a dessein : c'est ce qui permet a
             * l'index unique ci-dessous de liberer une place annulee sans
             * index partiel. Voir l'en-tete de cette migration.
             */
            $table->string('seat_number', 6)->nullable();
            $table->string('released_seat_number', 6)->nullable();

            $table->string('passenger_name');
            $table->string('passenger_phone', 20)->nullable();

            $table->string('status', 20)->default('issued');

            /*
             * Signature HMAC tronquee du billet et version de cle l'ayant
             * produite. Elles voyagent dans le QR code : l'application agent
             * peut ainsi verifier l'authenticite d'un billet **hors ligne**,
             * sans interroger le serveur. La version permet de faire tourner le
             * secret sans invalider les billets deja en circulation.
             */
            $table->string('signature', 64);
            $table->unsignedTinyInteger('key_version')->default(1);

            // Embarquement. `scanned_at` est la garantie anti-double-scan :
            // non nul, le billet est consomme.
            $table->dateTime('scanned_at')->nullable();
            $table->foreignUuid('scanned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('scanned_station_id')->nullable()->constrained('stations')->nullOnDelete();

            /*
             * Idempotence du scan hors ligne : la tablette rejoue ses scans au
             * retour du reseau, parfois plusieurs fois. Le serveur reconnait un
             * scan deja enregistre a cette reference et ne le compte pas comme
             * une seconde tentative frauduleuse.
             */
            $table->string('scan_client_reference', 64)->nullable();

            $table->timestamps();

            // --- La contrainte qui porte tout le domaine ---------------------
            $table->unique(['trip_id', 'seat_number'], 'tickets_trip_seat_unique');

            $table->index(['trip_id', 'status']);
            $table->index('scanned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
