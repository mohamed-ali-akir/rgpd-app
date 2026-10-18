<?php

use App\Services\Web\FetchException;
use App\Services\Web\FetchFailure;
use App\Services\Web\HostResolver;
use App\Services\Web\PageFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeHostResolver;

/*
 * PageFetcher réel, réseau simulé par Http::fake (et DNS simulé par FakeHostResolver).
 */

function fetchFailure(string $url): FetchFailure
{
    try {
        app(PageFetcher::class)->fetch($url);
    } catch (FetchException $e) {
        return $e->failure;
    }
    throw new RuntimeException('Aucune erreur levée.');
}

it('lit une page HTML et en extrait le texte', function () {
    Http::fake([
        'exemple.fr/robots.txt' => Http::response('', 404),
        'exemple.fr/profil' => Http::response('<title>Profil</title><p>Camille Martin</p>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
    ]);

    $page = app(PageFetcher::class)->fetch('https://exemple.fr/profil');

    expect($page->title)->toBe('Profil')->and($page->text)->toBe('Camille Martin');
    Http::assertSent(fn ($r) => $r->hasHeader('User-Agent') && str_contains($r->header('User-Agent')[0], 'EffaceMoiBot'));
});

it('suit une redirection relative en revérifiant chaque étape', function () {
    Http::fake([
        'exemple.fr/robots.txt' => Http::response('', 404),
        'exemple.fr/ancienne' => Http::response('', 301, ['Location' => '/nouvelle']),
        'exemple.fr/nouvelle' => Http::response('<p>Ici</p>', 200, ['Content-Type' => 'text/html']),
    ]);

    expect(app(PageFetcher::class)->fetch('https://exemple.fr/ancienne')->finalUrl)->toBe('https://exemple.fr/nouvelle');
});

it('refuse une redirection vers le réseau interne', function () {
    $this->app->instance(HostResolver::class, new FakeHostResolver(['interne.exemple.fr' => ['10.0.0.8']]));
    Http::fake([
        'exemple.fr/robots.txt' => Http::response('', 404),
        'exemple.fr/piege' => Http::response('', 302, ['Location' => 'https://interne.exemple.fr/admin']),
    ]);

    expect(fetchFailure('https://exemple.fr/piege'))->toBe(FetchFailure::UnsafeUrl);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'interne'));
});

it('s\'arrête après trop de redirections', function () {
    Http::fake([
        'exemple.fr/robots.txt' => Http::response('', 404),
        'exemple.fr/boucle' => Http::response('', 302, ['Location' => '/boucle']),
    ]);

    expect(fetchFailure('https://exemple.fr/boucle'))->toBe(FetchFailure::TooManyRedirects);
});

it('respecte robots.txt sans lire la page interdite', function () {
    Http::fake([
        'exemple.fr/robots.txt' => Http::response("User-agent: *\nDisallow: /annuaire/", 200),
        'exemple.fr/annuaire/*' => Http::response('<p>x</p>', 200, ['Content-Type' => 'text/html']),
    ]);

    expect(fetchFailure('https://exemple.fr/annuaire/camille'))->toBe(FetchFailure::RobotsDisallowed);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/annuaire/'));
});

it('s\'abstient si robots.txt est en erreur serveur, et le met en cache', function () {
    Http::fake([
        'exemple.fr/robots.txt' => Http::response('', 503),
        'exemple.fr/*' => Http::response('<p>x</p>', 200, ['Content-Type' => 'text/html']),
    ]);

    expect(fetchFailure('https://exemple.fr/a'))->toBe(FetchFailure::RobotsDisallowed)
        ->and(fetchFailure('https://exemple.fr/b'))->toBe(FetchFailure::RobotsDisallowed);
    Http::assertSentCount(1); // robots.txt demandé une seule fois
});

it('distingue page disparue, erreur HTTP et contenu non HTML', function (int $status, array $headers, FetchFailure $expected) {
    Http::fake([
        'exemple.fr/robots.txt' => Http::response('', 404),
        'exemple.fr/page' => Http::response('contenu', $status, $headers),
    ]);

    expect(fetchFailure('https://exemple.fr/page'))->toBe($expected);
})->with([
    '404' => [404, [], FetchFailure::NotFound],
    '410' => [410, [], FetchFailure::NotFound],
    '500' => [500, [], FetchFailure::HttpError],
    '403' => [403, [], FetchFailure::HttpError],
    'PDF' => [200, ['Content-Type' => 'application/pdf'], FetchFailure::NotHtml],
]);

it('refuse une page trop grosse, même sans Content-Length', function () {
    config(['scanner.fetch.max_bytes' => 1000]);
    Http::fake([
        'exemple.fr/robots.txt' => Http::response('', 404),
        'exemple.fr/enorme' => Http::response(str_repeat('a', 5000), 200, ['Content-Type' => 'text/html']),
    ]);

    expect(fetchFailure('https://exemple.fr/enorme'))->toBe(FetchFailure::TooLarge);
});

it('traduit une erreur réseau en page injoignable', function () {
    Http::fake([
        'exemple.fr/robots.txt' => Http::response('', 404),
        'exemple.fr/lent' => fn () => throw new ConnectionException('timeout'),
    ]);

    expect(fetchFailure('https://exemple.fr/lent'))->toBe(FetchFailure::Unreachable);
});
