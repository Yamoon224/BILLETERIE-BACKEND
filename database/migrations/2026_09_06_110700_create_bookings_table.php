<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservations : un acte d'achat, en ligne ou au guichet.
 *
 * Une reservation regroupe un a plusieurs billets (une famille achete quatre
 * places en une fois) et porte l'encaissement. Les places, elles, vivent dans
 * `tickets` : c'est la seule facon qu'une annulation partielle libere
 * exactement les sieges concernes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Reference dictable au telephone et lisible sur un ticket
            // thermique : c'est elle que le voyageur donne au guichet.
            $table->string('reference', 24)->unique();

            $table->foreignUuid('trip_id')->constrained('trips')->restrictOnDelete();

            // Recopiee depuis le depart : le cloisonnement par compagnie est
            // un filtre de securite, il ne doit pas dependre d'une jointure.
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();

            $table->string('channel', 20);
            $table->string('status', 20)->default('pending');

            // Coordonnees du client. Le telephone est obligatoire : c'est par
            // lui que part le billet, et c'est la seule identite dont dispose
            // un voyageur sans adresse e-mail.
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('customer_email')->nullable();

            // Compte voyageur, quand la reservation vient d'un utilisateur
            // connecte. Nul pour un achat sans compte et pour toute vente au
            // guichet : le guichet ne cree pas de compte a la place du client.
            $table->foreignUuid('customer_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Agent qui a vendu, et gare ou la vente a eu lieu.
            $table->foreignUuid('sold_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('station_id')->nullable()->constrained('stations')->nullOnDelete();

            $table->unsignedTinyInteger('seats_count');
            $table->unsignedInteger('total_amount');
            $table->char('currency', 3);

            /*
             * Commission plateforme figee au moment de la vente, avec son taux.
             * Sans le taux archive, une revision de bareme rendrait toutes les
             * commissions passees inexplicables — et c'est exactement le
             * chiffre qu'une compagnie partenaire viendra contester.
             */
            $table->unsignedInteger('commission_amount')->default(0);
            $table->unsignedSmallInteger('commission_per_mille')->default(0);

            /*
             * Echeance du blocage de places. Depassee sans paiement, la
             * reservation expire et les places retournent a la vente.
             * Nulle pour une vente au guichet, qui est payee sur-le-champ.
             */
            $table->dateTime('expires_at')->nullable();

            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            /*
             * Idempotence de la synchronisation hors ligne.
             *
             * L'application agent attribue cette reference **avant** d'avoir le
             * reseau. Si la reponse du serveur se perd et que la tablette
             * rejoue l'envoi, l'unicite garantit qu'aucune seconde vente n'est
             * creee : le serveur reconnait la vente et renvoie la premiere.
             * Sans cela, une coupure reseau au mauvais moment vend deux fois la
             * meme place au meme voyageur.
             */
            $table->string('client_reference', 64)->nullable()->unique();

            // Heure reelle de la vente au guichet, qui peut preceder de
            // plusieurs heures son arrivee sur le serveur.
            $table->dateTime('sold_offline_at')->nullable();
            $table->dateTime('synced_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'status']);
            $table->index(['company_id', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->index('customer_phone');
            $table->index(['sold_by_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
