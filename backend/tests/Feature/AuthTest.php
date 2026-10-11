<?php

use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('inscrit un utilisateur, crée son profil chiffré et envoie le lien de vérification', function () {
    Notification::fake();

    fromSpa()->postJson('/api/register', [
        'first_name' => 'Camille',
        'last_name' => 'Martin',
        'email' => 'camille@exemple.test',
        'password' => 'motdepasse-solide',
        'password_confirmation' => 'motdepasse-solide',
    ])->assertCreated()->assertJson(['email' => 'camille@exemple.test', 'email_verified' => false]);

    $user = User::where('email', 'camille@exemple.test')->firstOrFail();
    expect($user->profile->first_name)->toBe('Camille');
    // En base, le prénom est chiffré : on ne le retrouve pas en clair.
    expect(DB::table('profiles')->value('first_name'))->not->toBe('Camille');

    Notification::assertSentTo($user, VerifyEmail::class);
    $this->assertAuthenticatedAs($user);
});

it('refuse une inscription invalide', function () {
    fromSpa()->postJson('/api/register', ['email' => 'pas-un-email', 'password' => 'court'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password']);
});

it('connecte un utilisateur avec les bons identifiants', function () {
    $user = User::factory()->create();

    fromSpa()->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJson(['id' => $user->id]);

    $this->assertAuthenticatedAs($user);
});

it('refuse un mauvais mot de passe', function () {
    $user = User::factory()->create();

    fromSpa()->postJson('/api/login', ['email' => $user->email, 'password' => 'mauvais'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('renvoie l\'utilisateur connecté', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/user')
        ->assertOk()
        ->assertJson(['email' => $user->email, 'email_verified' => true, 'google_email' => null]);
});

it('refuse /api/user à un invité', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('déconnecte l\'utilisateur', function () {
    $user = User::factory()->create();

    fromSpa()->actingAs($user)->postJson('/api/logout')->assertNoContent();

    $this->assertGuest('web');
});

it('vérifie l\'adresse e-mail via le lien signé', function () {
    $user = User::factory()->unverified()->create();
    $url = (new VerifyEmail)->verificationUrl($user);

    $this->get($url)->assertRedirect('/?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('refuse un lien de vérification falsifié', function () {
    $user = User::factory()->unverified()->create();

    $this->get('/api/email/verify/'.$user->id.'/'.sha1($user->email))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('renvoie les erreurs de validation en français', function () {
    fromSpa()->postJson('/api/register', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.first_name.0', 'Le champ prénom est obligatoire.');
});

it('limite les tentatives de connexion avec un message en français', function () {
    foreach (range(1, 6) as $attempt) {
        fromSpa()->postJson('/api/login', ['email' => 'x@exemple.test', 'password' => 'mauvais']);
    }

    fromSpa()->postJson('/api/login', ['email' => 'x@exemple.test', 'password' => 'mauvais'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Trop de tentatives. Réessayez dans quelques minutes.');
});
