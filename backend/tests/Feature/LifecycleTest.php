<?php

use App\Enums\EventType;
use App\Enums\RequestStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\ErasureRequest;
use App\Notifications\DeadlinePassed;
use Illuminate\Support\Facades\Notification;

describe('transitionTo()', function () {
    it('change le statut et enregistre l\'événement correspondant', function () {
        $request = ErasureRequest::factory()->status(RequestStatus::Sent)->create();

        $request->transitionTo(RequestStatus::Refused, [], ['note' => 'Refus motivé']);

        expect($request->fresh()->status)->toBe(RequestStatus::Refused)
            ->and($request->events()->first()->type->value)->toBe('response_refused')
            ->and($request->events()->first()->payload)->toBe(['note' => 'Refus motivé']);
    });

    it('refuse une transition interdite sans rien enregistrer', function () {
        $request = ErasureRequest::factory()->create();

        expect(fn () => $request->transitionTo(RequestStatus::Complaint))->toThrow(InvalidTransitionException::class);
        expect($request->fresh()->status)->toBe(RequestStatus::Draft)
            ->and($request->events()->count())->toBe(0);
    });

    it('interdit de modifier le statut en dehors de transitionTo()', function () {
        $request = ErasureRequest::factory()->create();
        $request->status = RequestStatus::Accepted;

        expect(fn () => $request->save())->toThrow(LogicException::class);
    });

    it('interdit de modifier un événement de l\'historique', function () {
        $event = ErasureRequest::factory()->create()->recordEvent(EventType::Created);

        expect(fn () => $event->update(['payload' => ['x' => 1]]))->toThrow(LogicException::class);
    });
});

describe('réponses de l\'organisme', function () {
    it('accepte la demande', function () {
        $request = ErasureRequest::factory()->status(RequestStatus::Sent)->create();

        $this->actingAs($request->user)
            ->postJson("/api/requests/{$request->id}/responses", ['outcome' => 'accepted', 'note' => 'Données supprimées'])
            ->assertOk()
            ->assertJson(['status' => 'accepted', 'verification' => 'pending', 'available_actions' => ['verify', 'close', 'delete']]);
    });

    it('prolonge l\'échéance à 3 mois après l\'envoi', function () {
        $request = ErasureRequest::factory()->status(RequestStatus::Sent)->create();
        $sentAt = $request->sent_at;

        $this->actingAs($request->user)
            ->postJson("/api/requests/{$request->id}/responses", ['outcome' => 'extended'])
            ->assertOk()
            ->assertJson(['status' => 'extended']);

        expect($request->fresh()->due_at->toDateTimeString())->toBe($sentAt->copy()->addMonthsNoOverflow(3)->toDateTimeString());
    });

    it('refuse une prolongation une fois le délai dépassé', function () {
        $request = ErasureRequest::factory()->status(RequestStatus::Overdue, '-40 days', '-10 days')->create();

        $this->actingAs($request->user)
            ->postJson("/api/requests/{$request->id}/responses", ['outcome' => 'extended'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('outcome');
    });

    it('refuse une réponse sur un brouillon', function () {
        $request = ErasureRequest::factory()->create();

        $this->actingAs($request->user)
            ->postJson("/api/requests/{$request->id}/responses", ['outcome' => 'accepted'])
            ->assertUnprocessable();
    });
});

describe('plainte CNIL', function () {
    it('prépare la plainte après un refus puis génère le PDF', function () {
        $request = ErasureRequest::factory()->status(RequestStatus::Refused)->create();

        $this->actingAs($request->user)
            ->postJson("/api/requests/{$request->id}/complaint")
            ->assertOk()
            ->assertJson(['status' => 'complaint'])
            ->assertJsonPath('available_actions', ['close', 'download_complaint', 'delete']);

        $this->actingAs($request->user)
            ->get("/api/requests/{$request->id}/complaint.pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('refuse la plainte tant que la demande est en attente de réponse', function () {
        $request = ErasureRequest::factory()->status(RequestStatus::Sent)->create();

        $this->actingAs($request->user)->postJson("/api/requests/{$request->id}/complaint")->assertConflict();
    });

    it('refuse le PDF sans plainte préparée', function () {
        $request = ErasureRequest::factory()->status(RequestStatus::Refused)->create();

        $this->actingAs($request->user)->get("/api/requests/{$request->id}/complaint.pdf")->assertConflict();
    });

    it('garde le PDF disponible après la clôture', function () {
        $request = ErasureRequest::factory()->status(RequestStatus::Refused)->create();
        $request->transitionTo(RequestStatus::Complaint);
        $request->transitionTo(RequestStatus::Closed);

        $this->actingAs($request->user)->get("/api/requests/{$request->id}/complaint.pdf")->assertOk();
    });
});

describe('vérification quotidienne des délais', function () {
    it('passe en retard les demandes dont l\'échéance est dépassée et prévient l\'utilisateur', function () {
        Notification::fake();
        $late = ErasureRequest::factory()->status(RequestStatus::Sent, '-40 days', '-1 day')->create();
        $lateExtended = ErasureRequest::factory()->status(RequestStatus::Extended, '-100 days', '-2 days')->create();
        $onTime = ErasureRequest::factory()->status(RequestStatus::Sent, '-5 days', '+25 days')->create();
        $draft = ErasureRequest::factory()->create();

        $this->artisan('requests:check-deadlines')->assertSuccessful();

        expect($late->fresh()->status)->toBe(RequestStatus::Overdue)
            ->and($lateExtended->fresh()->status)->toBe(RequestStatus::Overdue)
            ->and($onTime->fresh()->status)->toBe(RequestStatus::Sent)
            ->and($draft->fresh()->status)->toBe(RequestStatus::Draft)
            ->and($late->events()->first()->type->value)->toBe('overdue');

        Notification::assertSentTo($late->user, DeadlinePassed::class);
        Notification::assertNotSentTo($onTime->user, DeadlinePassed::class);
    });

    it('ne traite pas deux fois la même demande', function () {
        Notification::fake();
        $late = ErasureRequest::factory()->status(RequestStatus::Sent, '-40 days', '-1 day')->create();

        $this->artisan('requests:check-deadlines');
        $this->artisan('requests:check-deadlines');

        Notification::assertSentToTimes($late->user, DeadlinePassed::class, 1);
    });
});

it('supprime automatiquement les demandes clôturées depuis plus de 12 mois', function () {
    $old = ErasureRequest::factory()->status(RequestStatus::Accepted)->create();
    $old->transitionTo(RequestStatus::Closed);
    ErasureRequest::whereKey($old->id)->toBase()->update(['updated_at' => now()->subMonths(13)]);
    $recent = ErasureRequest::factory()->status(RequestStatus::Accepted)->create();
    $recent->transitionTo(RequestStatus::Closed);

    $this->artisan('model:prune', ['--model' => [ErasureRequest::class]]);

    expect(ErasureRequest::find($old->id))->toBeNull()
        ->and(ErasureRequest::find($recent->id))->not->toBeNull();
});
