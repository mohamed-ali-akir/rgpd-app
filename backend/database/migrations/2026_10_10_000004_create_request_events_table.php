<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historique en ajout seul : une ligne n'est jamais modifiée (pas de updated_at).
        Schema::create('request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('erasure_request_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // EventType
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['erasure_request_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_events');
    }
};
