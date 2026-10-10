<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OrganizationSeeder::class);

        // Compte de démonstration, uniquement en développement local.
        if (app()->environment('local')) {
            User::factory()->create([
                'name' => 'Camille Demo',
                'email' => 'demo@example.test',
                // mot de passe : "password" (voir UserFactory)
            ]);
        }
    }
}
