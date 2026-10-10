<?php

use App\Enums\RequestStatus as S;

it('autorise les transitions prévues', function (S $from, S $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    'brouillon → envoyée' => [S::Draft, S::Sent],
    'envoyée → prolongée' => [S::Sent, S::Extended],
    'envoyée → acceptée' => [S::Sent, S::Accepted],
    'envoyée → refusée' => [S::Sent, S::Refused],
    'envoyée → en retard' => [S::Sent, S::Overdue],
    'prolongée → en retard' => [S::Extended, S::Overdue],
    'en retard → plainte' => [S::Overdue, S::Complaint],
    'en retard → acceptée' => [S::Overdue, S::Accepted],
    'refusée → plainte' => [S::Refused, S::Complaint],
    'refusée → clôturée' => [S::Refused, S::Closed],
    'acceptée → clôturée' => [S::Accepted, S::Closed],
    'plainte → clôturée' => [S::Complaint, S::Closed],
]);

it('interdit les autres transitions', function (S $from, S $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    'brouillon → acceptée' => [S::Draft, S::Accepted],
    'brouillon → plainte' => [S::Draft, S::Complaint],
    'envoyée → plainte' => [S::Sent, S::Complaint],
    'prolongée → prolongée' => [S::Extended, S::Extended],
    'en retard → prolongée' => [S::Overdue, S::Extended],
    'acceptée → plainte' => [S::Accepted, S::Complaint],
    'clôturée → brouillon' => [S::Closed, S::Draft],
    'clôturée → envoyée' => [S::Closed, S::Sent],
]);

it('ne permet aucune sortie du statut clôturé', function () {
    expect(S::Closed->allowedTransitions())->toBe([]);
});
