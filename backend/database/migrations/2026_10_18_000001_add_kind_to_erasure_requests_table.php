<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erasure_requests', function (Blueprint $table) {
            // Valeur par défaut "erasure" : les demandes existantes de la V1 restent valides sans migration de données.
            $table->string('kind')->default('erasure')->after('organization_id'); // RequestKind
            // Correction demandée (rectification) ou motif (limitation). Chiffré : peut contenir des données personnelles.
            $table->text('details')->nullable()->after('legal_grounds');
        });
    }

    public function down(): void
    {
        Schema::table('erasure_requests', function (Blueprint $table) {
            $table->dropColumn(['kind', 'details']);
        });
    }
};
