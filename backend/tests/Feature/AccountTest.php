<?php

use App\Enums\EventType;
use App\Enums\RequestStatus;
use App\Models\ErasureRequest;
use App\Models\GoogleAccount;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\RequestEvent;
use App\Models\User;

it('affiche et met à jour le profil', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/profile', [
        'first_name' => 'Camille',
        'last_name' => 'Martin',
        'postal_address' => '12 rue des Lilas, 75011 Paris',
        'phone' => null,
    ])->assertOk()->assertJson(['first_name' => 'Camille', 'postal_address' => '12 rue des Lilas, 75011 Paris']);

    $this->actingAs($user)->getJson('/api/profile')->assertOk()->assertJson(['last_name' => 'Martin', 'phone' => null]);
});

it('exporte toutes mes données en JSON, sans le jeton Google', function () {
    $user = verifiedUserWithGmail();
    ErasureRequest::factory()->for($user)->create()->recordEvent(EventType::Created);

    $response = $this->actingAs($user)->get('/api/me/export')
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename="mes-donnees.json"');

    expect($response->json('account.email'))->toBe($user->email)
        ->and($response->json('profile.first_name'))->toBe($user->profile->first_name)
        ->and($response->json('gmail.email'))->toBe('moi@gmail.test')
        ->and($response->json('erasure_requests'))->toHaveCount(1)
        ->and($response->getContent())->not->toContain('fake-refresh-token');
});

it('supprime le compte et tout son contenu en cascade', function () {
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->status(RequestStatus::Sent)->create();
    $request->recordEvent(EventType::Sent);
    $organization = Organization::factory()->create(['created_by' => $user->id]);
    $otherUser = User::factory()->create();
    ErasureRequest::factory()->for($otherUser)->create();

    fromSpa()->actingAs($user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect(User::find($user->id))->toBeNull()
        ->and(Profile::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(GoogleAccount::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(ErasureRequest::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(RequestEvent::where('erasure_request_id', $request->id)->exists())->toBeFalse()
        // L'organisme créé reste (il sert aux autres), mais n'est plus rattaché à l'utilisateur.
        ->and($organization->fresh()->created_by)->toBeNull()
        // Les données des autres utilisateurs ne sont pas touchées.
        ->and(ErasureRequest::where('user_id', $otherUser->id)->count())->toBe(1);

    $this->assertGuest('web');
});

it('exige le mot de passe pour supprimer le compte', function () {
    $user = User::factory()->create();

    fromSpa()->actingAs($user)->deleteJson('/api/me', ['password' => 'mauvais'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(User::find($user->id))->not->toBeNull();
});

it('déconnecte le compte Gmail', function () {
    $user = verifiedUserWithGmail();

    $this->actingAs($user)->deleteJson('/api/google-account')->assertNoContent();

    expect($user->fresh()->googleAccount)->toBeNull();
});

it('stocke le refresh token Google chiffré', function () {
    $user = verifiedUserWithGmail();

    expect(DB::table('google_accounts')->value('refresh_token'))->not->toBe('fake-refresh-token')
        ->and($user->googleAccount->refresh_token)->toBe('fake-refresh-token');
});
