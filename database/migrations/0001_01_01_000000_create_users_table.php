<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            // Identifiant public : il circule dans les URL, les journaux et les
            // exports. Un entier sequentiel y annoncerait le nombre de comptes
            // ouverts et rendrait le suivant devinable.
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();

            /*
             * Le telephone est l'identite reelle des voyageurs ici : beaucoup
             * n'ont pas d'adresse e-mail, et c'est par ce numero que part le
             * billet electronique. Il est donc unique au meme titre que
             * l'e-mail, mais reste nullable pour les comptes de back-office
             * crees par un administrateur.
             */
            $table->string('phone', 20)->nullable()->unique();

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Un compte desactive conserve son historique de ventes : on ne
            // supprime pas un agent qui a encaisse, on lui retire l'acces.
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
