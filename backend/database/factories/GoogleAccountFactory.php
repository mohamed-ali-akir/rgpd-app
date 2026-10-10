<?php

namespace Database\Factories;

use App\Http\Controllers\Api\GoogleAccountController;
use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleAccount>
 */
class GoogleAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->unique()->userName().'@gmail.test',
            'refresh_token' => 'fake-refresh-token',
            'scopes' => [GoogleAccountController::GMAIL_SEND_SCOPE],
        ];
    }
}
