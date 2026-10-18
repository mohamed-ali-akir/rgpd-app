<?php

use App\Models\ErasureRequest;
use App\Models\User;

function kindPayload(array $overrides = []): array
{
    return array_merge([
        'url' => 'https://annuaire.exemple.test/camille',
        'organization_id' => organization()->id,
        'own_data_confirmed' => true,
    ], $overrides);
}

it('crée une demande d\'accès sans type de donnée ni fondement', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/requests', kindPayload(['kind' => 'access']))
        ->assertCreated()
        ->assertJson(['kind' => 'access', 'data_types' => [], 'legal_grounds' => [], 'details' => null])
        ->assertJsonPath('events.0.payload.kind', 'access');
});

it('exige la correction demandée pour une rectification', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/requests', kindPayload(['kind' => 'rectification', 'data_types' => ['name']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('details');
});

it('crée une rectification et chiffre la correction en base', function () {
    $response = $this->actingAs(User::factory()->create())
        ->postJson('/api/requests', kindPayload(['kind' => 'rectification', 'data_types' => ['name'], 'details' => 'Mon nom s\'écrit Martín.']))
        ->assertCreated()
        ->assertJson(['kind' => 'rectification', 'details' => 'Mon nom s\'écrit Martín.']);

    expect($response->json('body'))->toContain('Mon nom s\'écrit Martín.')
        ->and(DB::table('erasure_requests')->value('details'))->not->toBe('Mon nom s\'écrit Martín.');
});

it('refuse des précisions sur un effacement', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/requests', kindPayload(['data_types' => ['name'], 'details' => 'inutile']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('details');
});

it('refuse de modifier les fondements d\'une demande qui n\'est pas un effacement', function () {
    $request = ErasureRequest::factory()->create(['kind' => 'access', 'legal_grounds' => []]);

    $this->actingAs($request->user)
        ->patchJson("/api/requests/{$request->id}", ['legal_grounds' => ['art17_1_c']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('legal_grounds');
});

it('les demandes de la V1 restent des effacements', function () {
    $request = ErasureRequest::factory()->create();

    expect($request->fresh()->kind->value)->toBe('erasure');
});
