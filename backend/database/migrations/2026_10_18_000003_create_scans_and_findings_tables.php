<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('trigger'); // ScanTrigger
            $table->string('status')->default('queued'); // ScanStatus
            // Résultat par source : {"company_registry": {"status": "ok", "found": 2}, "web_search": {"status": "failed", ...}}
            $table->json('report')->nullable();
            $table->unsignedInteger('new_findings_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Dernier scan qui a vu ce résultat.
            $table->foreignId('scan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source'); // FindingSource
            $table->text('url');
            // Empreinte de l'URL normalisée : clé de déduplication (une URL peut dépasser la taille d'un index).
            $table->char('url_hash', 64);
            $table->string('domain');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            // Titre, extrait et détails : données personnelles, chiffrés (cast "encrypted").
            $table->text('title')->nullable();
            $table->text('snippet')->nullable();
            $table->text('meta')->nullable();
            $table->json('matched'); // DataType trouvés
            $table->unsignedTinyInteger('confidence');
            $table->string('status')->default('new'); // FindingStatus
            $table->foreignId('erasure_request_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // Un même résultat n'existe qu'une fois par utilisateur, même s'il est retrouvé à chaque scan.
            $table->unique(['user_id', 'url_hash']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'decided_at']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            // Scan mensuel automatique : désactivé par défaut (consentement explicite).
            $table->boolean('auto_scan')->default(false)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('auto_scan');
        });
        Schema::dropIfExists('findings');
        Schema::dropIfExists('scans');
    }
};
