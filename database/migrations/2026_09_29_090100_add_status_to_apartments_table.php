<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Statut de validation d'une fiche, symetrique de celui des compagnies. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('is_active');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
