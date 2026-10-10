<?php

use App\Enums\Channel;
use App\Enums\LegalGround;
use App\Enums\OrganizationCategory;
use App\Models\Organization;
use App\Services\LegalAdvisor;

/**
 * Organisme construit en mémoire (pas enregistré) : le test n'a pas besoin de base de données.
 */
function org(OrganizationCategory $category, ?string $email = 'dpo@exemple.test', bool $verified = true): Organization
{
    $organization = new Organization(['name' => 'Exemple', 'domain' => 'exemple.test', 'category' => $category, 'privacy_email' => $email]);
    $organization->verified_at = $verified ? now() : null;

    return $organization;
}

function warningCodes(Organization $organization): array
{
    return array_column((new LegalAdvisor)->warnings($organization), 'code');
}

it('suggère par défaut l\'opposition (art. 21) avec l\'effacement art. 17.1.c', function () {
    $advice = (new LegalAdvisor)->advise(org(OrganizationCategory::Directory));

    expect($advice->legalGrounds)->toBe([LegalGround::Objection])
        ->and($advice->warnings)->toBe([])
        ->and($advice->suggestedChannel)->toBe(Channel::Gmail);
});

it('ajoute le retrait du consentement si l\'utilisateur a publié lui-même ou a un compte', function (bool $selfPublished, bool $hasAccount) {
    $advice = (new LegalAdvisor)->advise(org(OrganizationCategory::Forum), $selfPublished, $hasAccount);

    expect($advice->legalGrounds)->toBe([LegalGround::Objection, LegalGround::ConsentWithdrawn]);
})->with([
    'publié lui-même' => [true, false],
    'a un compte' => [false, true],
    'les deux' => [true, true],
]);

it('avertit d\'un refus probable pour un registre légal', function () {
    expect(warningCodes(org(OrganizationCategory::LegalRegistry)))->toBe(['legal_publication']);
});

it('avertit sur la liberté d\'expression pour la presse', function () {
    expect(warningCodes(org(OrganizationCategory::Press)))->toBe(['freedom_of_expression']);
});

it('impose le canal manuel pour un moteur de recherche', function () {
    $advice = (new LegalAdvisor)->advise(org(OrganizationCategory::SearchEngine));

    expect($advice->suggestedChannel)->toBe(Channel::Manual)
        ->and(array_column($advice->warnings, 'code'))->toBe(['dereferencing_form']);
});

it('suggère le canal manuel quand l\'organisme n\'a pas d\'e-mail', function () {
    expect((new LegalAdvisor)->suggestedChannel(org(OrganizationCategory::Directory, email: null)))->toBe(Channel::Manual);
});

it('signale des coordonnées non vérifiées', function () {
    expect(warningCodes(org(OrganizationCategory::Directory, verified: false)))->toBe(['unverified_contact']);
});
