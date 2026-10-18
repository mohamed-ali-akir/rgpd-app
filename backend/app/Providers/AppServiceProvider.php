<?php

namespace App\Providers;

use App\Services\Mail\FakeSender;
use App\Services\Mail\GmailSender;
use App\Services\Mail\MailSender;
use App\Services\Web\DnsHostResolver;
use App\Services\Web\HostResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Le code dépend de l'interface MailSender ; le conteneur fournit l'implémentation.
        // Singleton : dans un test, on récupère la même FakeSender pour vérifier ce qui a été "envoyé".
        $this->app->singleton(MailSender::class, fn () => match (config('services.mail_sender')) {
            'gmail' => new GmailSender,
            default => new FakeSender,
        });

        // Résolution DNS réelle ; les tests la remplacent par une table fixe (aucun accès réseau).
        $this->app->bind(HostResolver::class, DnsHostResolver::class);
    }

    public function boot(): void
    {
        // Les API Resources renvoient l'objet directement, sans l'envelopper dans { "data": ... }.
        JsonResource::withoutWrapping();

        /*
         * Formulaires accessibles sans être connecté (inscription, connexion, mot de passe oublié) :
         * 6 essais par minute, comptés séparément pour CHAQUE formulaire et chaque adresse IP.
         * Avec "throttle:6,1", le compteur était commun aux trois routes : 6 échecs de connexion
         * bloquaient aussi le formulaire « mot de passe oublié ».
         */
        RateLimiter::for('guest-forms', fn (Request $request) => Limit::perMinute(6)->by($request->path().'|'.$request->ip()));
    }
}
