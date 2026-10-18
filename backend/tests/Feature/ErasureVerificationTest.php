<?php

use App\Enums\RequestStatus;
use App\Enums\VerificationStatus;
use App\Jobs\VerifyErasureJob;
use App\Models\ErasureRequest;
use App\Models\User;
use App\Notifications\ErasureStillPresent;
use App\Services\ErasureVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * Demande d'effacement acceptée par l'organisme, dont la page est https://annuaire.exemple.test/camille.
 */
function acceptedErasure(array $attributes = []): ErasureRequest
{
    $user = User::factory()->create(['name' => 'Camille Martin']);
    $user->profile->update(['first_name' => 'Camille', 'last_name' => 'Martin', 'phone' => '06 01 02 03 04']);
    $request = ErasureRequest::factory()->for($user)
        ->status(RequestStatus::Sent)
        ->create(['url' => 'https://annuaire.exemple.test/camille', 'data_types' => ['name', 'phone'], ...$attributes]);
    $request->transitionTo(RequestStatus::Accepted);

    return $request->fresh();
}

function pageServes(string $body, int $status = 200): void
{
    Http::fake([
        'annuaire.exemple.test/robots.txt' => Http::response('', 404),
        'annuaire.exemple.test/camille' => Http::response($body, $status, ['Content-Type' => 'text/html']),
    ]);
}

it('programme une vérification quand un effacement est accepté', function () {
    $this->freezeTime();
    $request = acceptedErasure();

    expect($request->verification)->toBe(VerificationStatus::Pending)
        ->and($request->next_check_at->toDateTimeString())->toBe(now()->addDays(3)->toDateTimeString());
});

it('ne programme rien pour une demande d\'accès acceptée', function () {
    $request = acceptedErasure(['kind' => 'access', 'data_types' => []]);

    expect($request->verification)->toBeNull();
});

it('valide l\'effacement quand la page a disparu', function () {
    pageServes('', 404);
    $request = acceptedErasure();

    $check = app(ErasureVerifier::class)->verify($request);

    expect($check->outcome->value)->toBe('gone')
        ->and($request->fresh()->verification)->toBe(VerificationStatus::Cleared)
        ->and($request->fresh()->next_check_at)->toBeNull()
        ->and($request->events()->pluck('type')->map->value->last())->toBe('erasure_verified');
});

it('valide l\'effacement quand la page ne contient plus les données', function () {
    pageServes('<p>Cette fiche a été supprimée.</p>');
    $request = acceptedErasure();

    app(ErasureVerifier::class)->verify($request);

    expect($request->fresh()->verification)->toBe(VerificationStatus::Cleared);
});

it('reprogramme puis alerte quand les données restent en ligne', function () {
    Notification::fake();
    $this->freezeTime();
    pageServes('<h1>MARTIN Camille</h1><p>Tél : 06.01.02.03.04</p>');
    $request = acceptedErasure();
    $verifier = app(ErasureVerifier::class);

    $check = $verifier->verify($request);
    expect($check->outcome->value)->toBe('present')
        ->and($check->matched)->toBe(['name', 'phone'])
        ->and($request->fresh()->verification)->toBe(VerificationStatus::Pending)
        ->and($request->fresh()->next_check_at->toDateTimeString())->toBe(now()->addDays(7)->toDateTimeString());
    Notification::assertNothingSent();

    foreach (range(2, 4) as $attempt) {
        $verifier->verify($request->fresh());
    }

    $request->refresh();
    expect($request->verification)->toBe(VerificationStatus::StillPresent)
        ->and($request->check_count)->toBe(4)
        ->and($request->availableActions())->toContain('complaint');
    Notification::assertSentToTimes($request->user, ErasureStillPresent::class, 1);
});

it('ne permet la plainte après acceptation que si les données sont toujours en ligne', function () {
    $request = acceptedErasure();

    $this->actingAs($request->user)->postJson("/api/requests/{$request->id}/complaint")->assertConflict();

    $request->forceFill(['verification' => VerificationStatus::StillPresent])->save();
    $this->actingAs($request->user)->postJson("/api/requests/{$request->id}/complaint")
        ->assertOk()
        ->assertJson(['status' => 'complaint']);
});

it('abandonne quand robots.txt interdit la page, sans la lire', function () {
    Http::fake([
        'annuaire.exemple.test/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200),
        'annuaire.exemple.test/camille' => Http::response('<p>Camille Martin</p>', 200),
    ]);
    $request = acceptedErasure();

    $check = app(ErasureVerifier::class)->verify($request);

    expect($check->outcome->value)->toBe('blocked')
        ->and($check->failure->value)->toBe('robots_disallowed')
        ->and($request->fresh()->verification)->toBe(VerificationStatus::Unverifiable);
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/camille'));
});

it('réessaie quand la page est injoignable, puis renonce', function () {
    pageServes('', 503);
    $request = acceptedErasure();
    $verifier = app(ErasureVerifier::class);

    $verifier->verify($request);
    expect($request->fresh()->verification)->toBe(VerificationStatus::Pending);

    $verifier->verify($request->fresh());
    $verifier->verify($request->fresh());
    expect($request->fresh()->verification)->toBe(VerificationStatus::Unverifiable);
});

it('ne lit jamais une page interne, même si l\'URL de la demande y pointe', function () {
    Http::fake();
    $request = acceptedErasure(['url' => 'http://127.0.0.1/admin']);

    $check = app(ErasureVerifier::class)->verify($request);

    expect($check->outcome->value)->toBe('blocked')->and($check->failure->value)->toBe('unsafe_url');
    Http::assertNothingSent();
});

it('met en file les vérifications dues, et seulement elles', function () {
    Queue::fake();
    $due = acceptedErasure();
    $due->forceFill(['next_check_at' => now()->subMinute()])->save();
    $notYet = acceptedErasure();

    $this->artisan('requests:verify-erasures')->assertSuccessful();

    Queue::assertPushed(VerifyErasureJob::class, 1);
    Queue::assertPushed(VerifyErasureJob::class, fn ($job) => $job->erasureRequest->is($due));
});

it('lance une vérification manuelle, au plus une par heure', function () {
    Queue::fake();
    $request = acceptedErasure();

    $this->actingAs($request->user)->postJson("/api/requests/{$request->id}/verify")->assertAccepted();
    $this->actingAs($request->user)->postJson("/api/requests/{$request->id}/verify")->assertTooManyRequests();

    Queue::assertPushed(VerifyErasureJob::class, fn ($job) => $job->manual);
});

it('refuse la vérification d\'une demande non acceptée', function () {
    $request = ErasureRequest::factory()->status(RequestStatus::Sent)->create();

    $this->actingAs($request->user)->postJson("/api/requests/{$request->id}/verify")->assertConflict();
});

it('expose les vérifications dans le détail de la demande', function () {
    pageServes('', 410);
    $request = acceptedErasure();
    app(ErasureVerifier::class)->verify($request);

    $this->actingAs($request->user)->getJson("/api/requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('verification', 'cleared')
        ->assertJsonPath('checks.0.outcome', 'gone')
        ->assertJsonPath('checks.0.http_status', 410);
});
