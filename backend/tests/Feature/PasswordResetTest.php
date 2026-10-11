<?php

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('envoie un lien de réinitialisation qui pointe vers la page React', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'camille@exemple.test']);

    fromSpa()->postJson('/api/forgot-password', ['email' => 'camille@exemple.test'])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        return str_contains($notification->resetUrl($user), '/reset-password/'.$notification->token.'?email=camille%40exemple.test');
    });
});

it('répond pareil pour une adresse inconnue, sans rien envoyer', function () {
    Notification::fake();
    User::factory()->create(['email' => 'camille@exemple.test']);

    $known = fromSpa()->postJson('/api/forgot-password', ['email' => 'camille@exemple.test'])->json('message');
    $unknown = fromSpa()->postJson('/api/forgot-password', ['email' => 'inconnu@exemple.test'])->assertOk()->json('message');

    // Impossible de savoir, depuis la réponse, si un compte existe.
    expect($unknown)->toBe($known);
    Notification::assertSentTimes(ResetPassword::class, 1);
});

it('change le mot de passe avec un jeton valide et ferme les autres sessions', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'ancienne-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $token = Password::createToken($user);

    fromSpa()->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertOk()->assertJsonPath('message', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

    expect(Hash::check('nouveau-mot-de-passe', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        // Le jeton est à usage unique.
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

it('refuse un jeton invalide', function () {
    $user = User::factory()->create();

    fromSpa()->postJson('/api/reset-password', [
        'token' => 'faux-jeton',
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau.');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('refuse un jeton expiré', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);
    $this->travel(61)->minutes();

    fromSpa()->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertUnprocessable();
});

it('applique les mêmes règles de mot de passe qu\'à l\'inscription', function () {
    $user = User::factory()->create();

    fromSpa()->postJson('/api/reset-password', [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'court',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('supprime le jeton de réinitialisation avec le compte', function () {
    $user = User::factory()->create();
    Password::createToken($user);

    fromSpa()->actingAs($user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});
