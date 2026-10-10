<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erasure_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete : on ne peut pas supprimer un organisme qui a des demandes.
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('url', 2048);
            $table->json('data_types'); // liste de DataType
            $table->json('legal_grounds'); // liste de LegalGround
            $table->string('channel'); // Channel
            $table->string('status')->default('draft'); // RequestStatus
            $table->string('subject');
            $table->text('body');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            // Liste filtrée par statut sur le tableau de bord.
            $table->index(['user_id', 'status']);
            // Requête quotidienne de CheckDeadlinesCommand : WHERE status IN (...) AND due_at < now.
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erasure_requests');
    }
};
