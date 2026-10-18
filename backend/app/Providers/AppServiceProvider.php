<?php

namespace App\Providers;

use App\Services\Demo\DemoScanSource;
use App\Services\Mail\FakeSender;
use App\Services\Mail\GmailSender;
use App\Services\Mail\MailSender;
use App\Services\Scanner\Scanner;
use App\Services\Scanner\Sources\CompanyRegistrySource;
use App\Services\Scanner\Sources\WebSearchSource;
use App\Services\Search\FakeSearchProvider;
use App\Services\Search\NullSearchProvider;
use App\Services\Search\SearchProvider;
use App\Services\Search\SearxngSearchProvider;
use App\Services\Web\DnsHostResolver;
use App\Services\Web\HostResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Le code dépend de l'interface MailSender ; le conteneur fournit l'implémentation.
        // Singleton : dans un test, on récupère la même FakeSender pour vérifier ce qui a été "envoyé".
        // En démo, jamais d'envoi réel, quelle que soit la configuration.
        $this->app->singleton(MailSender::class, fn () => match (config('app.demo') ? 'fake' : config('services.mail_sender')) {
            'gmail' => new GmailSender,
            default => new FakeSender,
        });

        // Résolution DNS réelle ; les tests la remplacent par une table fixe (aucun accès réseau).
        $this->app->bind(HostResolver::class, DnsHostResolver::class);

        // Moteur de recherche du scanner, choisi par SEARCH_PROVIDER (aucun par défaut).
        $this->app->singleton(SearchProvider::class, fn () => match (config('scanner.search.provider')) {
            'searxng' => new SearxngSearchProvider(config('scanner.search.searxng_url')),
            'fake' => new FakeSearchProvider,
            default => new NullSearchProvider,
        });

        // Sources du scanner : ajouter une source = ajouter sa classe ici. En démo : données fictives uniquement.
        $this->app->when(Scanner::class)->needs('$sources')->give(fn ($app) => config('app.demo')
            ? [$app->make(DemoScanSource::class)]
            : [$app->make(CompanyRegistrySource::class), $app->make(WebSearchSource::class)]);
    }

    public function boot(): void
    {
        // Les API Resources renvoient l'objet directement, sans l'envelopper dans { "data": ... }.
        JsonResource::withoutWrapping();

        /*
         * Application servie sous un chemin (démo sur akir.alwaysdata.net/efface-moi) : l'hébergeur retire
         * ce chemin avant de transmettre la requête, Laravel se croit donc à la racine. On force APP_URL
         * pour que les liens générés (e-mails, redirections après Google ou la vérification d'e-mail)
         * gardent le chemin. À la racine d'un domaine, rien ne change.
         */
        $appUrl = (string) config('app.url');
        if (trim((string) parse_url($appUrl, PHP_URL_PATH), '/') !== '') {
            URL::forceRootUrl(rtrim($appUrl, '/'));
            URL::forceScheme((string) parse_url($appUrl, PHP_URL_SCHEME));
        }

        /*
         * Formulaires accessibles sans être connecté (inscription, connexion, mot de passe oublié) :
         * 6 essais par minute, comptés séparément pour CHAQUE formulaire et chaque adresse IP.
         * Avec "throttle:6,1", le compteur était commun aux trois routes : 6 échecs de connexion
         * bloquaient aussi le formulaire « mot de passe oublié ».
         */
        RateLimiter::for('guest-forms', fn (Request $request) => Limit::perMinute(6)->by($request->path().'|'.$request->ip()));
    }
}
