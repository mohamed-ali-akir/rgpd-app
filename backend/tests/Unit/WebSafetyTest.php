<?php

use App\Services\Web\RobotsTxt;
use App\Services\Web\UnsafeUrlException;
use App\Services\Web\UrlGuard;
use App\Services\Web\UrlNormalizer;
use Tests\Support\FakeHostResolver;

describe('UrlGuard (protection SSRF)', function () {
    it('accepte une URL publique et donne l\'IP à épingler', function () {
        $guarded = (new UrlGuard(new FakeHostResolver))->check('https://exemple.fr/page');

        expect($guarded->host)->toBe('exemple.fr')
            ->and($guarded->port)->toBe(443)
            ->and($guarded->curlResolveEntry())->toBe('exemple.fr:443:'.FakeHostResolver::PUBLIC_IP);
    });

    it('refuse les adresses internes, directes ou via le DNS', function (string $url, array $dns = []) {
        (new UrlGuard(new FakeHostResolver($dns)))->check($url);
    })->throws(UnsafeUrlException::class)->with([
        'localhost en IP' => ['http://127.0.0.1/admin'],
        'métadonnées cloud' => ['http://169.254.169.254/latest/meta-data'],
        'réseau privé' => ['http://192.168.1.1/'],
        'IPv6 locale' => ['http://[::1]/'],
        'IPv4 dans IPv6' => ['http://[::ffff:127.0.0.1]/'],
        'nom qui pointe vers le réseau interne' => ['https://piege.exemple.fr/', ['piege.exemple.fr' => ['10.0.0.5']]],
        'une seule IP interne suffit' => ['https://mixte.exemple.fr/', ['mixte.exemple.fr' => ['93.184.215.14', '10.0.0.5']]],
        'plage opérateur (100.64/10)' => ['http://100.64.0.1/'],
    ]);

    it('refuse les schémas, ports et identifiants non autorisés', function (string $url) {
        (new UrlGuard(new FakeHostResolver))->check($url);
    })->throws(UnsafeUrlException::class)->with([
        'file' => ['file:///etc/passwd'],
        'gopher' => ['gopher://exemple.fr/'],
        'port exotique' => ['http://exemple.fr:8080/'],
        'identifiants' => ['https://admin:secret@exemple.fr/'],
    ]);

    it('épingle une IPv6 entre crochets', function () {
        $guarded = (new UrlGuard(new FakeHostResolver(['v6.exemple.fr' => ['2606:4700::1111']])))->check('https://v6.exemple.fr/');

        expect($guarded->curlResolveEntry())->toBe('v6.exemple.fr:443:[2606:4700::1111]');
    });
});

it('signale un domaine sans adresse', function () {
    (new UrlGuard(new FakeHostResolver(['vide.exemple.fr' => []])))->check('https://vide.exemple.fr/');
})->throws(UnsafeUrlException::class, 'Nom de domaine introuvable.');

describe('UrlNormalizer', function () {
    it('reconnaît deux écritures de la même page', function (string $a, string $b) {
        $normalizer = new UrlNormalizer;

        expect($normalizer->normalize($a))->toBe($normalizer->normalize($b))
            ->and($normalizer->hash($a))->toBe($normalizer->hash($b));
    })->with([
        'casse et port par défaut' => ['HTTPS://Exemple.FR:443/page', 'https://exemple.fr/page'],
        'barre finale et ancre' => ['https://exemple.fr/page/#haut', 'https://exemple.fr/page'],
        'paramètres de suivi' => ['https://exemple.fr/p?utm_source=x&id=3&fbclid=y', 'https://exemple.fr/p?id=3'],
        'ordre des paramètres' => ['https://exemple.fr/p?b=2&a=1', 'https://exemple.fr/p?a=1&b=2'],
    ]);

    it('distingue deux pages différentes', function () {
        $normalizer = new UrlNormalizer;

        expect($normalizer->hash('https://exemple.fr/p?id=3'))->not->toBe($normalizer->hash('https://exemple.fr/p?id=4'));
    });

    it('extrait le domaine sans www', function () {
        expect((new UrlNormalizer)->domain('https://www.PagesJaunes.fr/pros/1'))->toBe('pagesjaunes.fr');
    });
});

describe('RobotsTxt', function () {
    $robots = <<<'TXT'
        User-agent: *
        Disallow: /prive/
        Allow: /prive/public
        Disallow: /*.pdf$

        User-agent: AutreBot
        Disallow: /
        TXT;

    it('applique la règle la plus précise', function (string $path, bool $allowed) use ($robots) {
        expect(RobotsTxt::parse($robots, 'EffaceMoiBot')->allows('https://exemple.fr'.$path))->toBe($allowed);
    })->with([
        'page libre' => ['/profil/camille', true],
        'dossier interdit' => ['/prive/dossier', false],
        'exception autorisée' => ['/prive/public/page', true],
        'joker et ancre de fin' => ['/docs/cv.pdf', false],
        'pdf suivi d\'un paramètre' => ['/docs/cv.pdf?v=2', true],
    ]);

    it('applique le groupe qui nous nomme plutôt que le groupe générique', function () {
        $rules = "User-agent: *\nDisallow:\n\nUser-agent: EffaceMoiBot\nDisallow: /annuaire/";

        expect(RobotsTxt::parse($rules, 'EffaceMoiBot')->allows('https://exemple.fr/annuaire/x'))->toBeFalse()
            ->and(RobotsTxt::parse($rules, 'EffaceMoiBot')->allows('https://exemple.fr/autre'))->toBeTrue();
    });

    it('gère plusieurs User-agent pour un même groupe', function () {
        $rules = "User-agent: AutreBot\nUser-agent: *\nDisallow: /";

        expect(RobotsTxt::parse($rules, 'EffaceMoiBot')->allows('https://exemple.fr/'))->toBeFalse();
    });
});
