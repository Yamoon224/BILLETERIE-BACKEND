<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des envois sortants (SMS, messagerie).
 *
 * Un envoi est trace meme quand il echoue, et surtout quand il echoue : sur un
 * reseau 2G intermittent, « le voyageur n'a pas recu son billet » est la
 * premiere reclamation du support, et sans ce journal la reponse serait une
 * conjecture. L'echec n'annule jamais la reservation — le billet reste
 * consultable et rejouable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('booking_id')->nullable()->constrained('bookings')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->nullable()->constrained('tickets')->cascadeOnDelete();

            $table->string('channel', 20);
            $table->string('recipient', 32);
            $table->string('template', 50);

            // Variables du message, pas le message rendu : un modele corrige
            // se rejoue alors sur les envois passes.
            $table->json('payload')->nullable();

            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error')->nullable();
            $table->dateTime('sent_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['booking_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
