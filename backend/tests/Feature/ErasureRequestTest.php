<?php

use App\Enums\EventType;
use App\Enums\OrganizationCategory;
use App\Enums\RequestStatus;
use App\Models\ErasureRequest;
use App\Models\RequestEvent;
use App\Models\User;

function validPayload(array $overrides = []): array
{
    return array_merge([
        'url' => 'https://annuaire.exemple.test/camille',
        'organization_id' => organization()->id,
        'data_types' => ['name', 'phone'],
        'self_published' => false,
        'has_account' => false,
        'own_data_confirmed' => true,
    ], $overrides);
}

it('crée un brouillon avec lettre générée, fondements suggérés et événement "created"', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/requests', validPayload(['has_account' => true]))
        ->assertCreated()
        ->assertJson([
            'status' => 'draft',
            'channel' => 'gmail',
            'legal_grounds' => ['art17_1_c', 'art17_1_b'],
            'available_actions' => ['edit', 'send', 'delete'],
        ]);

    expect($response->json('body'))->toContain($user->profile->first_name)
        ->and($response->json('events.0.type'))->toBe('created');
});

it('renvoie les avertissements juridiques sans bloquer la création', function () {
    $press = organization(['category' => OrganizationCategory::Press]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/requests', validPayload(['organization_id' => $press->id]))
        ->assertCreated()
        ->assertJsonPath('warnings.0.code', 'freedom_of_expression');
});

it('exige la case "mes propres données"', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/requests', validPayload(['own_data_confirmed' => false]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('own_data_confirmed');
});

it('refuse le canal Gmail pour un organisme sans e-mail', function () {
    $org = organization(['privacy_email' => null]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/requests', validPayload(['organization_id' => $org->id, 'channel' => 'gmail']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('channel');
});

it('modifie le texte d\'un brouillon', function () {
    $request = ErasureRequest::factory()->create();

    $this->actingAs($request->user)
        ->patchJson("/api/requests/{$request->id}", ['body' => 'Mon texte modifié'])
        ->assertOk()
        ->assertJson(['body' => 'Mon texte modifié']);
});

it('régénère la lettre après changement des fondements', function () {
    $request = ErasureRequest::factory()->create();

    $response = $this->actingAs($request->user)
        ->patchJson("/api/requests/{$request->id}", ['legal_grounds' => ['art17_1_d'], 'regenerate_letter' => true])
        ->assertOk();

    expect($response->json('body'))->toContain('art. 17.1.d')->not->toContain('article 21');
});

it('refuse de modifier une demande déjà envoyée', function () {
    $request = ErasureRequest::factory()->status(RequestStatus::Sent)->create();

    $this->actingAs($request->user)
        ->patchJson("/api/requests/{$request->id}", ['body' => 'Trop tard'])
        ->assertConflict();
});

it('liste uniquement mes demandes, filtrables par statut', function () {
    $user = User::factory()->create();
    ErasureRequest::factory()->for($user)->create();
    ErasureRequest::factory()->for($user)->status(RequestStatus::Sent)->create();
    ErasureRequest::factory()->create(); // appartient à quelqu'un d'autre

    $this->actingAs($user)->getJson('/api/requests')->assertOk()->assertJsonCount(2);
    $this->actingAs($user)->getJson('/api/requests?status=sent')->assertOk()->assertJsonCount(1);
});

it('supprime une demande et son historique', function () {
    $request = ErasureRequest::factory()->create();
    $request->recordEvent(EventType::Created);

    $this->actingAs($request->user)->deleteJson("/api/requests/{$request->id}")->assertNoContent();

    expect(ErasureRequest::count())->toBe(0)
        ->and(RequestEvent::count())->toBe(0);
});

it('renvoie les compteurs du tableau de bord', function () {
    $user = User::factory()->create();
    ErasureRequest::factory()->for($user)->count(2)->create();
    ErasureRequest::factory()->for($user)->status(RequestStatus::Overdue, '-40 days', '-10 days')->create();

    $this->actingAs($user)->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('counts.draft', 2)
        ->assertJsonPath('counts.overdue', 1)
        ->assertJsonPath('counts.closed', 0)
        ->assertJsonCount(1, 'overdue');
});
