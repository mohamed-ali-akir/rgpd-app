<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erasure_requests', function (Blueprint $table) {
            // Marqueur posé juste AVANT l'appel à Gmail et retiré une fois l'envoi enregistré (SendErasureRequestJob).
            // S'il est encore là au nouvel essai, Gmail a peut-être déjà envoyé la lettre : on ne la renvoie pas.
            $table->timestamp('sending_started_at')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('erasure_requests', function (Blueprint $table) {
            $table->dropColumn('sending_started_at');
        });
    }
};
