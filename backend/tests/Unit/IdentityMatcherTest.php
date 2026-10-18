<?php

use App\Enums\DataType;
use App\Services\Identity\Identity;
use App\Services\Identity\IdentityMatcher;
use App\Services\Web\HtmlTextExtractor;

function camille(?string $phone = '06 01 02 03 04'): Identity
{
    return new Identity('Camille-Marie', 'Martín', ['camille.martin@exemple.test'], $phone);
}

it('reconnaît le nom malgré les accents, la casse, les tirets et l\'ordre', function (string $text) {
    expect((new IdentityMatcher)->match(camille(), $text)->dataTypes)->toBe([DataType::Name]);
})->with([
    'ordre naturel' => ['Fiche de Camille-Marie Martín, Paris'],
    'NOM Prénom' => ['MARTIN Camille Marie — 12 rue des Lilas'],
    'sans accent' => ['camille marie martin'],
]);

it('ne confond pas avec un autre nom', function (string $text) {
    expect((new IdentityMatcher)->match(camille(), $text)->found())->toBeFalse();
})->with([
    'prénom seul' => ['Camille-Marie Dupont'],
    'nom collé à un autre mot' => ['Camille Marie Martinez'],
    'texte vide' => [''],
]);

it('reconnaît l\'e-mail sans faux positif', function () {
    $matcher = new IdentityMatcher;

    expect($matcher->containsEmail(camille(), 'Contact : Camille.Martin@Exemple.test.'))->toBeTrue()
        ->and($matcher->containsEmail(camille(), 'xcamille.martin@exemple.test'))->toBeFalse()
        ->and($matcher->containsEmail(camille(), 'camille.martin@exemple.testing'))->toBeFalse();
});

it('reconnaît le téléphone sous tous ses formats', function (string $text, bool $expected) {
    expect((new IdentityMatcher)->containsPhone('06 01 02 03 04', $text))->toBe($expected);
})->with([
    'collé' => ['Tél : 0601020304', true],
    'espaces' => ['06 01 02 03 04', true],
    'points' => ['06.01.02.03.04', true],
    'tirets' => ['06-01-02-03-04', true],
    'international' => ['+33 6 01 02 03 04', true],
    'international (0)' => ['+33 (0)6 01 02 03 04', true],
    '0033' => ['0033601020304', true],
    'autre numéro' => ['06 01 02 03 05', false],
    'noyé dans un nombre' => ['106010203049', false],
]);

it('combine les indices dans la confiance', function () {
    $matcher = new IdentityMatcher;

    $name = $matcher->match(camille(), 'Camille-Marie Martín');
    $namePhone = $matcher->match(camille(), 'Camille-Marie Martín, 06 01 02 03 04');
    $email = $matcher->match(camille(), 'camille.martin@exemple.test');

    expect($name->confidence)->toBe(50)
        ->and($namePhone->confidence)->toBe(90)
        ->and($namePhone->dataTypes)->toBe([DataType::Name, DataType::Phone])
        ->and($email->confidence)->toBe(95);
});

it('ignore un téléphone de profil qui n\'est pas un numéro français', function () {
    expect((new IdentityMatcher)->containsPhone('12345', 'Tél 12345'))->toBeFalse();
});

it('extrait le texte visible d\'une page sans scripts ni styles', function () {
    $html = '<html><head><title>Fiche &amp; contact</title><style>.x{color:red}</style></head>'
        .'<body><script>var secret = 1;</script><h1>Camille   Martín</h1><p>Tél&nbsp;: 06 01 02 03 04</p></body></html>';

    $page = (new HtmlTextExtractor)->extract($html);

    expect($page['title'])->toBe('Fiche & contact')
        ->and($page['text'])->toBe('Camille Martín Tél : 06 01 02 03 04')
        ->and($page['text'])->not->toContain('secret');
});
