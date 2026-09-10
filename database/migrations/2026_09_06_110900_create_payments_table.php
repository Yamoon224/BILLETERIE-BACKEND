<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encaissements.
 *
 * Le cahier des charges interdit de stocker la moindre donnee de paiement en
 * propre : cette table n'en contient aucune. Elle conserve un montant, un
 * moyen, un statut et une **reference externe** rendue par l'agregateur — de
 * quoi rapprocher une recette, jamais de quoi rejouer un debit. Le numero du
 * payeur (`payer_msisdn`) est un numero de telephone, pas un instrument de
 * paiement : il sert au support client et au remboursement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 24)->unique();

            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();

            $table->string('method', 20);
            $table->string('provider', 30)->nullable();
            $table->string('gateway', 30);

            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 20)->default('pending');

            /*
             * Reference rendue par l'agregateur. Indexee parce que c'est la
             * seule cle dont dispose un webhook entrant : il ne connait pas nos
             * identifiants internes.
             */
            $table->string('external_reference', 120)->nullable();

            $table->string('payer_msisdn', 20)->nullable();

            // Agent qui a encaisse, pour les especes au guichet.
            $table->foreignUuid('collected_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * Reponse brute de l'agregateur, conservee telle quelle. C'est la
             * piece justificative d'un litige : reconstituer a posteriori ce
             * que l'operateur avait reellement repondu est impossible si l'on
             * n'a garde que notre interpretation.
             */
            $table->json('gateway_payload')->nullable();
            $table->string('failure_reason')->nullable();

            $table->dateTime('authorized_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('refunded_at')->nullable();

            // Idempotence de la remontee hors ligne, comme sur les
            // reservations : un encaissement rejoue ne doit pas doubler la
            // recette de la journee.
            $table->string('client_reference', 64)->nullable()->unique();

            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['external_reference']);
            $table->index(['status', 'created_at']);
            $table->index(['method', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
