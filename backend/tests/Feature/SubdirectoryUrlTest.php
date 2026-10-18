<?php

use App\Providers\AppServiceProvider;

/*
 * Démo servie sur akir.alwaysdata.net/efface-moi : les liens générés par Laravel doivent garder
 * ce chemin, y compris hors requête web (e-mails envoyés par le planificateur).
 */

it('garde le chemin de APP_URL dans les liens et les redirections', function () {
    config(['app.url' => 'https://akir.alwaysdata.net/efface-moi']);
    (new AppServiceProvider(app()))->boot();

    expect(url('/requests/3'))->toBe('https://akir.alwaysdata.net/efface-moi/requests/3')
        ->and(redirect('/settings?google=connected')->getTargetUrl())
        ->toBe('https://akir.alwaysdata.net/efface-moi/settings?google=connected');
});

it('ne change rien quand l\'application est à la racine du domaine', function () {
    $before = url('/requests/3');
    config(['app.url' => 'https://efface-moi.example']);
    (new AppServiceProvider(app()))->boot();

    // Sans chemin dans APP_URL, Laravel continue d'utiliser l'adresse de la requête.
    expect(url('/requests/3'))->toBe($before);
});
