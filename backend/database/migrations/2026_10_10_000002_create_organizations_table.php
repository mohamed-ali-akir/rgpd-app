<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique(); // ex. "pagesjaunes.fr", sans "www."
            $table->string('category'); // OrganizationCategory
            $table->string('privacy_email')->nullable();
            $table->string('privacy_form_url', 2048)->nullable();
            $table->string('dpo_name')->nullable();
            // Rempli uniquement quand un humain a vérifié les contacts à la main.
            $table->timestamp('verified_at')->nullable();
            // null = créé par le seeder. Si l'utilisateur supprime son compte, l'organisme reste.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
