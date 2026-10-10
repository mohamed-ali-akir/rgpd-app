<?php

use App\Enums\RequestStatus;
use App\Http\Controllers\Api\ErasureRequestActionController;
use App\Jobs\SendErasureRequestJob;
use App\Models\ErasureRequest;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
 * En test, QUEUE_CONNECTION=sync (phpunit.xml) : le job s'exécute immédiatement,
 * et MailSender est une FakeSender qui garde les messages en mémoire.
 */

it('envoie la demande via Gmail, passe en "sent" et calcule l\'échéance', function () {
    $this->freezeTime();
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->create(['subject' => 'Objet', 'body' => 'Corps de la lettre']);

    $this->actingAs($user)->postJson("/api/requests/{$request->id}/send")->assertAccepted();

    $request->refresh();
    expect($request->status)->toBe(RequestStatus::Sent)
        ->and($request->sent_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($request->due_at->toDateTimeString())->toBe(now()->addMonthNoOverflow()->toDateTimeString())
        ->and($request->events->pluck('type.value')->all())->toBe(['sent']);

    expect(fakeSender()->sent)->toHaveCount(1)
        ->and(fakeSender()->sent[0])->toMatchArray([
            'to' => $request->organization->privacy_email,
            'subject' => 'Objet',
            'body' => 'Corps de la lettre',
        ]);
});

it('met le job en file sans l\'exécuter dans la requête HTTP', function () {
    Queue::fake();
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->create();

    $this->actingAs($user)->postJson("/api/requests/{$request->id}/send")->assertAccepted();

    Queue::assertPushed(SendErasureRequestJob::class, fn ($job) => $job->erasureRequest->is($request));
    expect($request->fresh()->status)->toBe(RequestStatus::Draft);
});

it('exige un e-mail vérifié pour envoyer', function () {
    $user = verifiedUserWithGmail();
    $user->forceFill(['email_verified_at' => null])->save();
    $request = ErasureRequest::factory()->for($user)->create();

    $this->actingAs($user)->postJson("/api/requests/{$request->id}/send")->assertForbidden();
    expect(fakeSender()->sent)->toBe([]);
});

it('exige un compte Gmail connecté', function () {
    $user = User::factory()->create();
    $request = ErasureRequest::factory()->for($user)->create();

    $this->actingAs($user)->postJson("/api/requests/{$request->id}/send")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Connectez votre compte Gmail dans les paramètres avant d\'envoyer.');
});

it('limite les envois Gmail à 10 par jour', function () {
    $user = verifiedUserWithGmail();
    $requests = ErasureRequest::factory()->for($user)->count(ErasureRequestActionController::DAILY_GMAIL_LIMIT + 1)->create();

    foreach ($requests->take(ErasureRequestActionController::DAILY_GMAIL_LIMIT) as $request) {
        $this->actingAs($user)->postJson("/api/requests/{$request->id}/send")->assertAccepted();
    }

    $this->actingAs($user)->postJson("/api/requests/{$requests->last()->id}/send")->assertTooManyRequests();
    expect(fakeSender()->sent)->toHaveCount(ErasureRequestActionController::DAILY_GMAIL_LIMIT);
});

it('enregistre un échec et déconnecte Gmail si Google a révoqué l\'accès', function () {
    fakeSender()->disconnected = true;
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->create();

    $this->actingAs($user)->postJson("/api/requests/{$request->id}/send")->assertAccepted();

    expect($request->fresh()->status)->toBe(RequestStatus::Draft)
        ->and($request->events()->first()->type->value)->toBe('send_failed')
        ->and($user->fresh()->googleAccount)->toBeNull();
});

it('refuse d\'envoyer via Gmail une demande au canal manuel', function () {
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->manual()->create();

    $this->actingAs($user)->postJson("/api/requests/{$request->id}/send")->assertConflict();
});

it('confirme un envoi manuel à une date passée', function () {
    $request = ErasureRequest::factory()->manual()->create();

    $this->actingAs($request->user)
        ->postJson("/api/requests/{$request->id}/mark-sent", ['sent_at' => '2026-01-31'])
        ->assertOk()
        ->assertJson(['status' => 'sent']);

    expect($request->fresh()->due_at->toDateString())->toBe('2026-02-28');
});

it('refuse une date d\'envoi dans le futur', function () {
    $request = ErasureRequest::factory()->manual()->create();

    $this->actingAs($request->user)
        ->postJson("/api/requests/{$request->id}/mark-sent", ['sent_at' => now()->addDay()->toDateString()])
        ->assertUnprocessable();
});

it('envoie une relance Gmail sans changer le statut', function () {
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->status(RequestStatus::Overdue, '-40 days', '-10 days')->create();

    $this->actingAs($user)->postJson("/api/requests/{$request->id}/reminder")->assertAccepted();

    expect($request->fresh()->status)->toBe(RequestStatus::Overdue)
        ->and($request->events()->pluck('type')->map->value->all())->toBe(['reminder_sent'])
        ->and(fakeSender()->sent[0]['subject'])->toStartWith('Relance');
});

it('enregistre une relance manuelle', function () {
    $request = ErasureRequest::factory()->manual()->status(RequestStatus::Overdue, '-40 days', '-10 days')->create();

    $this->actingAs($request->user)->postJson("/api/requests/{$request->id}/reminder")->assertOk();

    expect($request->events()->first()->payload)->toBe(['channel' => 'manual']);
});

it('refuse une relance avant l\'échéance', function () {
    $request = ErasureRequest::factory()->manual()->status(RequestStatus::Sent)->create();

    $this->actingAs($request->user)->postJson("/api/requests/{$request->id}/reminder")->assertConflict();
});
