<?php

use App\Enums\DataType;
use App\Services\Identity\Identity;
use App\Services\Scanner\Sources\CompanyRegistrySource;
use App\Services\Scanner\Sources\SourceSkippedException;
use App\Services\Scanner\Sources\WebSearchSource;
use App\Services\Search\FakeSearchProvider;
use App\Services\Search\SearchProvider;
use App\Services\Search\SearchResult;
use App\Services\Search\SearchUnavailableException;
use Illuminate\Support\Facades\Http;

function scanIdentity(): Identity
{
    return new Identity('Camille', 'Martin', ['camille.martin@exemple.test'], '06 01 02 03 04');
}

function useSearch(array $results): FakeSearchProvider
{
    $provider = new FakeSearchProvider($results);
    app()->instance(SearchProvider::class, $provider);

    return $provider;
}

describe('CompanyRegistrySource', function () {
    it('ne garde que les entreprises où l\'utilisateur est bien dirigeant', function () {
        Http::fake(['recherche-entreprises.api.gouv.fr/*' => Http::response(['results' => [
            ['siren' => '123456789', 'nom_complet' => 'MARTIN CONSEIL', 'dirigeants' => [
                ['nom' => 'MARTIN', 'prenoms' => 'CAMILLE MARIE', 'qualite' => 'Gérant', 'annee_de_naissance' => '1990', 'type_dirigeant' => 'personne physique'],
            ]],
            // Renvoyée par l'API pour un autre dirigeant homonyme partiel : doit être ignorée.
            ['siren' => '987654321', 'nom_complet' => 'AUTRE SAS', 'dirigeants' => [
                ['nom' => 'MARTIN', 'prenoms' => 'PAUL', 'qualite' => 'Président'],
            ]],
        ]])]);

        $candidates = app(CompanyRegistrySource::class)->scan(scanIdentity());

        expect($candidates)->toHaveCount(1)
            ->and($candidates[0]->url)->toBe('https://annuaire-entreprises.data.gouv.fr/entreprise/123456789')
            ->and($candidates[0]->dataTypes)->toBe([DataType::Name, DataType::BirthDate])
            ->and($candidates[0]->meta)->toMatchArray(['siren' => '123456789', 'role' => 'Gérant']);
        Http::assertSent(fn ($r) => $r['nom_personne'] === 'Martin' && $r['prenoms_personne'] === 'Camille' && $r['type_personne'] === 'dirigeant');
    });

    it('signale une panne de l\'API (la source sera notée en échec)', function () {
        Http::fake(['recherche-entreprises.api.gouv.fr/*' => Http::response('', 503)]);

        app(CompanyRegistrySource::class)->scan(scanIdentity());
    })->throws(RuntimeException::class);

    it('ignore une réponse inattendue sans planter', function () {
        Http::fake(['recherche-entreprises.api.gouv.fr/*' => Http::response(['results' => [['pas' => 'de siren'], 'texte']])]);

        expect(app(CompanyRegistrySource::class)->scan(scanIdentity()))->toBe([]);
    });
});

describe('WebSearchSource', function () {
    it('est sautée quand aucun moteur n\'est configuré', function () {
        app(WebSearchSource::class)->scan(scanIdentity());
    })->throws(SourceSkippedException::class);

    it('cherche le nom, l\'e-mail et le téléphone', function () {
        $search = useSearch([]);

        app(WebSearchSource::class)->scan(scanIdentity());

        expect($search->queries)->toBe(['"Camille Martin"', '"camille.martin@exemple.test"', '"06 01 02 03 04"']);
    });

    it('confirme en relisant la page, et écarte les pages qui ne parlent pas de l\'utilisateur', function () {
        useSearch(['"Camille Martin"' => [
            new SearchResult('https://annuaire.exemple.fr/camille', 'Annuaire', 'extrait'),
            new SearchResult('https://autre.exemple.fr/page', 'Camille Martin ?', 'extrait trompeur'),
        ]]);
        Http::fake([
            '*/robots.txt' => Http::response('', 404),
            'annuaire.exemple.fr/camille' => Http::response('<h1>Camille Martin</h1><p>06 01 02 03 04</p>', 200, ['Content-Type' => 'text/html']),
            'autre.exemple.fr/page' => Http::response('<p>Une autre personne</p>', 200, ['Content-Type' => 'text/html']),
        ]);

        $candidates = app(WebSearchSource::class)->scan(scanIdentity());

        expect($candidates)->toHaveCount(1)
            ->and($candidates[0]->url)->toBe('https://annuaire.exemple.fr/camille')
            ->and($candidates[0]->dataTypes)->toBe([DataType::Name, DataType::Phone])
            ->and($candidates[0]->confidence)->toBe(90)
            ->and($candidates[0]->meta['verified'])->toBeTrue();
    });

    it('se rabat sur l\'extrait, avec moins de confiance, quand la page est interdite aux robots', function () {
        useSearch(['"Camille Martin"' => [new SearchResult('https://reseau.exemple.fr/u/1', 'Camille Martin — profil', 'Camille Martin, Lyon')]]);
        Http::fake(['reseau.exemple.fr/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200)]);

        $candidates = app(WebSearchSource::class)->scan(scanIdentity());

        expect($candidates)->toHaveCount(1)
            ->and($candidates[0]->confidence)->toBe(35) // 50 × 0,7
            ->and($candidates[0]->meta)->toBe(['verified' => false, 'unverified_reason' => 'robots_disallowed']);
    });

    it('dédoublonne les résultats et ignore les pages de l\'application', function () {
        config(['app.url' => 'https://efface-moi.exemple']);
        useSearch(['*' => [
            new SearchResult('https://annuaire.exemple.fr/camille?utm_source=x', 'Camille Martin', 'Camille Martin'),
            new SearchResult('https://efface-moi.exemple/privacy', 'Camille Martin', 'Camille Martin'),
        ]]);
        Http::fake([
            '*/robots.txt' => Http::response('', 404),
            'annuaire.exemple.fr/*' => Http::response('<p>Camille Martin</p>', 200, ['Content-Type' => 'text/html']),
        ]);

        $candidates = app(WebSearchSource::class)->scan(scanIdentity());

        expect($candidates)->toHaveCount(1);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'efface-moi.exemple'));
    });

    it('respecte le budget de pages relues', function () {
        config(['scanner.scan.max_pages_fetched' => 1]);
        useSearch(['"Camille Martin"' => [
            new SearchResult('https://a.exemple.fr/1', 'Camille Martin', 'Camille Martin'),
            new SearchResult('https://b.exemple.fr/2', 'Camille Martin', 'Camille Martin'),
        ]]);
        Http::fake([
            '*/robots.txt' => Http::response('', 404),
            '*' => Http::response('<p>Camille Martin</p>', 200, ['Content-Type' => 'text/html']),
        ]);

        $candidates = app(WebSearchSource::class)->scan(scanIdentity());

        expect(collect($candidates)->pluck('meta.verified')->all())->toBe([true, false]);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'b.exemple.fr'));
    });

    it('est en échec seulement si toutes les requêtes échouent', function () {
        useSearch([])->failing = true;

        app(WebSearchSource::class)->scan(scanIdentity());
    })->throws(SearchUnavailableException::class);
});
