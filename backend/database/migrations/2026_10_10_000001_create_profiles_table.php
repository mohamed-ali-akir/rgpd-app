<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            // unique() : relation 1-1, un seul profil par utilisateur.
            // cascadeOnDelete() : supprimer l'utilisateur supprime son profil.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Colonnes "text" : une valeur chiffrée est bien plus longue que la valeur d'origine.
            $table->text('first_name');
            $table->text('last_name');
            $table->text('postal_address')->nullable();
            $table->text('phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
