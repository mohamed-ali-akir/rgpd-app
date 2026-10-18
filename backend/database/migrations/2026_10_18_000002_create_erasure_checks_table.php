<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // État de la vérification, recopié sur la demande pour que la tâche quotidienne trouve
        // les demandes à vérifier par une simple requête indexée (sans parcourir l'historique).
        Schema::table('erasure_requests', function (Blueprint $table) {
            $table->string('verification')->nullable()->after('due_at'); // VerificationStatus
            $table->timestamp('next_check_at')->nullable()->after('verification');
            $table->unsignedTinyInteger('check_count')->default(0)->after('next_check_at');

            $table->index(['verification', 'next_check_at']);
        });

        // Historique des vérifications (ajout seul, comme request_events).
        Schema::create('erasure_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('erasure_request_id')->constrained()->cascadeOnDelete();
            $table->string('outcome'); // CheckOutcome
            $table->string('failure')->nullable(); // FetchFailure si la page n'a pas pu être lue
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('matched')->nullable(); // données encore trouvées sur la page
            $table->boolean('manual')->default(false);
            $table->timestamp('checked_at');

            $table->index(['erasure_request_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erasure_checks');
        Schema::table('erasure_requests', function (Blueprint $table) {
            $table->dropIndex(['verification', 'next_check_at']);
            $table->dropColumn(['verification', 'next_check_at', 'check_count']);
        });
    }
};
