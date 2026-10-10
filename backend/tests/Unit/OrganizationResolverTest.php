<?php

use App\Services\OrganizationResolver;

it('extrait le domaine d\'une URL', function (string $url, string $domain) {
    expect((new OrganizationResolver)->extractDomain($url))->toBe($domain);
})->with([
    'URL complète' => ['https://www.PagesJaunes.fr/pros/123?x=1', 'pagesjaunes.fr'],
    'sans schéma' => ['linkedin.com/in/camille', 'linkedin.com'],
    'sous-domaine conservé' => ['https://fr.linkedin.com/in/camille', 'fr.linkedin.com'],
    'point final' => ['http://exemple.fr./page', 'exemple.fr'],
]);

it('refuse une URL sans domaine', function () {
    (new OrganizationResolver)->extractDomain('pas une url');
})->throws(InvalidArgumentException::class);
