<?php

use App\Enums\DataType;
use App\Enums\LegalGround;
use App\Enums\OrganizationCategory;
use App\Enums\RequestKind;
use App\Models\Organization;
use App\Services\LegalAdvisor;
use App\Services\LetterGenerator;
use App\Services\Letters\LetterData;
use Carbon\CarbonImmutable;

function kindLetter(RequestKind $kind, array $dataTypes = [DataType::Name, DataType::Phone], ?string $details = null): LetterData
{
    return new LetterData(
        firstName: 'Camille',
        lastName: 'Martin',
        email: 'camille.martin@exemple.test',
        postalAddress: null,
        phone: null,
        organizationName: 'Annuaire Exemple',
        dpoName: null,
        url: 'https://annuaire.exemple.test/camille-martin',
        dataTypes: $dataTypes,
        legalGrounds: [],
        date: CarbonImmutable::parse('2026-10-18'),
        kind: $kind,
        details: $details,
    );
}

it('génère la lettre d\'accès (art. 15)', function () {
    $letter = (new LetterGenerator)->initial(kindLetter(RequestKind::Access, []));

    expect($letter->subject)->toBe('Demande d\'accès à mes données personnelles (article 15 du RGPD)')
        ->and($letter->body)->toContain('l\'ensemble des données me concernant')
        ->and($letter->body)->not->toContain('article 17')
        ->and($letter->body)->toMatchSnapshot();
});

it('génère la lettre de rectification (art. 16) avec la correction demandée', function () {
    $letter = (new LetterGenerator)->initial(kindLetter(RequestKind::Rectification, [DataType::PostalAddress], 'Remplacer « 12 rue des Lilas » par « 14 rue des Lilas ».'));

    expect($letter->subject)->toContain('article 16')
        ->and($letter->body)->toContain('Remplacer « 12 rue des Lilas » par « 14 rue des Lilas ».')
        ->and($letter->body)->toMatchSnapshot();
});

it('génère la lettre de limitation (art. 18) avec le motif', function () {
    $letter = (new LetterGenerator)->initial(kindLetter(RequestKind::Restriction, [DataType::Name], 'Je conteste l\'exactitude de ces données.'));

    expect($letter->subject)->toContain('article 18')
        ->and($letter->body)->toContain('Je conteste l\'exactitude de ces données.')
        ->and($letter->body)->toMatchSnapshot();
});

it('adapte la relance au droit exercé', function (RequestKind $kind, string $expected) {
    $letter = (new LetterGenerator)->reminder(kindLetter($kind), CarbonImmutable::parse('2026-08-31'), CarbonImmutable::parse('2026-09-30'));

    expect($letter->subject)->toStartWith('Relance : ')
        ->and($letter->body)->toContain($expected);
})->with([
    'accès' => [RequestKind::Access, 'me communiquer une copie de ces données'],
    'rectification' => [RequestKind::Rectification, 'procéder à la rectification de ces données'],
    'limitation' => [RequestKind::Restriction, 'limiter le traitement de ces données'],
]);

it('ne propose les motifs de l\'art. 17 et ses avertissements que pour l\'effacement', function () {
    $press = new Organization(['name' => 'Journal', 'domain' => 'journal.test', 'category' => OrganizationCategory::Press, 'privacy_email' => 'dpo@journal.test']);
    $press->verified_at = now();
    $advisor = new LegalAdvisor;

    $access = $advisor->advise($press, kind: RequestKind::Access);
    $erasure = $advisor->advise($press, kind: RequestKind::Erasure);

    expect($access->legalGrounds)->toBe([])
        ->and($access->warnings)->toBe([])
        ->and($erasure->legalGrounds)->toBe([LegalGround::Objection])
        ->and(array_column($erasure->warnings, 'code'))->toBe(['freedom_of_expression']);
});
