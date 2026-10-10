<?php

use App\Http\Controllers\Api\GoogleAccountController;
use App\Models\Organization;
use App\Models\User;
use App\Services\Mail\FakeSender;
use App\Services\Mail\MailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Tests fonctionnels : application démarrée + base SQLite en mémoire,
 * remise à zéro à chaque test (RefreshDatabase).
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Tests unitaires : l'application est démarrée (pour view(), now()…) mais sans base de données.
 */
pest()->extend(TestCase::class)->in('Unit');

/**
 * Simule une requête venant du SPA : Sanctum n'active la session que si le Referer
 * correspond à un domaine "stateful" (SANCTUM_STATEFUL_DOMAINS).
 */
function fromSpa(): TestCase
{
    return test()->withHeader('Referer', 'http://localhost:5173');
}

function verifiedUserWithGmail(): User
{
    $user = User::factory()->create();
    $user->googleAccount()->create([
        'email' => 'moi@gmail.test',
        'refresh_token' => 'fake-refresh-token',
        'scopes' => [GoogleAccountController::GMAIL_SEND_SCOPE],
    ]);

    return $user;
}

function fakeSender(): FakeSender
{
    return app(MailSender::class);
}

function organization(array $attributes = []): Organization
{
    return Organization::factory()->create($attributes);
}
