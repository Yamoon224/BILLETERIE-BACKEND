<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verification en deux etapes (TOTP), reservee en pratique aux comptes
 * administrateur : le secret n'existe qu'une fois l'inscription confirmee par
 * un premier code valide (`two_factor_confirmed_at`), pour ne jamais exiger un
 * code sur un compte dont l'application d'authentification n'a en realite
 * jamais ete configuree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};
