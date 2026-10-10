<?php

use App\Models\ErasureRequest;
use App\Models\User;

/**
 * Un utilisateur ne peut ni voir ni modifier les demandes d'un autre.
 */
beforeEach(function () {
    $this->otherRequest = ErasureRequest::factory()->create();
    $this->actingAs(User::factory()->create());
});

it('interdit chaque route sur la demande d\'un autre', function (string $method, string $uri) {
    $this->json($method, str_replace('{id}', $this->otherRequest->id, $uri))->assertForbidden();
})->with([
    'voir' => ['GET', '/api/requests/{id}'],
    'modifier' => ['PATCH', '/api/requests/{id}'],
    'supprimer' => ['DELETE', '/api/requests/{id}'],
    'envoyer' => ['POST', '/api/requests/{id}/send'],
    'confirmer l\'envoi' => ['POST', '/api/requests/{id}/mark-sent'],
    'relancer' => ['POST', '/api/requests/{id}/reminder'],
    'saisir une réponse' => ['POST', '/api/requests/{id}/responses'],
    'plainte' => ['POST', '/api/requests/{id}/complaint'],
    'clôturer' => ['POST', '/api/requests/{id}/close'],
    'PDF' => ['GET', '/api/requests/{id}/complaint.pdf'],
]);

it('ne modifie pas la demande de l\'autre', function () {
    $this->patchJson("/api/requests/{$this->otherRequest->id}", ['body' => 'piraté']);

    expect($this->otherRequest->fresh()->body)->not->toBe('piraté');
});
