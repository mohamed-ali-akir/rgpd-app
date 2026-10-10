<?php

use App\Enums\DataType;
use App\Enums\LegalGround;
use App\Services\LetterGenerator;
use App\Services\Letters\LetterData;
use Carbon\CarbonImmutable;

/**
 * Tests par "snapshot" : au premier lancement, Pest enregistre le texte obtenu dans
 * tests/.pest/snapshots ; ensuite, il vérifie que la lettre n'a pas changé d'un caractère.
 * Si on modifie volontairement un modèle : relire le nouveau texte puis lancer
 * "vendor/bin/pest --update-snapshots".
 */
function letterData(array $overrides = []): LetterData
{
    return new LetterData(...array_merge([
        'firstName' => 'Camille',
        'lastName' => 'Martin',
        'email' => 'camille.martin@exemple.test',
        'postalAddress' => null,
        'phone' => null,
        'organizationName' => 'Annuaire Exemple',
        'dpoName' => null,
        'url' => 'https://annuaire.exemple.test/camille-martin',
        'dataTypes' => [DataType::Name, DataType::PostalAddress, DataType::Phone],
        'legalGrounds' => [LegalGround::Objection],
        'date' => CarbonImmutable::parse('2026-10-10'),
    ], $overrides));
}

it('génère la lettre par défaut (opposition + art. 17.1.c)', function () {
    $letter = (new LetterGenerator)->erasure(letterData());

    expect($letter->subject)->toContain('articles 21 et 17')
        ->and($letter->body)->toMatchSnapshot();
});

it('génère une lettre complète (adresse, téléphone, DPO, plusieurs fondements)', function () {
    $letter = (new LetterGenerator)->erasure(letterData([
        'postalAddress' => "12 rue des Lilas\n75011 Paris",
        'phone' => '06 00 00 00 00',
        'dpoName' => 'Service DPO',
        'legalGrounds' => [LegalGround::Objection, LegalGround::ConsentWithdrawn],
    ]));

    expect($letter->body)->toMatchSnapshot();
});

it('n\'évoque pas l\'article 21 sans opposition', function () {
    $letter = (new LetterGenerator)->erasure(letterData(['legalGrounds' => [LegalGround::UnlawfulProcessing]]));

    expect($letter->subject)->toBe('Demande d\'effacement de données personnelles (article 17 du RGPD)')
        ->and($letter->body)->not->toContain('article 21')
        ->and($letter->body)->toContain('traitement illicite (art. 17.1.d).');
});

it('produit exactement la même lettre pour la même entrée', function () {
    $generator = new LetterGenerator;

    expect($generator->erasure(letterData())->body)->toBe($generator->erasure(letterData())->body);
});

it('n\'échappe pas les apostrophes en entités HTML', function () {
    expect((new LetterGenerator)->erasure(letterData())->body)->not->toContain('&#039;');
});

it('génère la relance', function () {
    $letter = (new LetterGenerator)->reminder(
        letterData(),
        CarbonImmutable::parse('2026-08-31'),
        CarbonImmutable::parse('2026-09-30'),
    );

    expect($letter->body)->toContain('Le 31 août 2026')
        ->and($letter->body)->toContain('a expiré le 30 septembre 2026')
        ->and($letter->body)->toMatchSnapshot();
});

it('formate la liste des données', function (array $types, string $expected) {
    expect((new LetterGenerator)->dataList($types))->toBe($expected);
})->with([
    'une seule' => [[DataType::Email], 'mon adresse e-mail'],
    'deux' => [[DataType::Name, DataType::Email], 'mes nom et prénom et mon adresse e-mail'],
    'trois' => [[DataType::Name, DataType::Email, DataType::Photo], 'mes nom et prénom, mon adresse e-mail et ma photographie'],
]);
