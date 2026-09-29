<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Code PIN de connexion rapide au guichet.
 *
 * Distinct du mot de passe : un agent en gare le saisit plusieurs fois par
 * jour sur une tablette, et quatre chiffres suffisent a cet usage-la. Sa
 * faible entropie est compensee par un blocage apres plusieurs echecs
 * (`pin_failed_attempts`, `pin_locked_until`), jamais par la longueur du
 * code lui-meme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('pin_code_hash')->nullable()->after('password');
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0)->after('pin_code_hash');
            $table->timestamp('pin_locked_until')->nullable()->after('pin_failed_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['pin_code_hash', 'pin_failed_attempts', 'pin_locked_until']);
        });
    }
};
