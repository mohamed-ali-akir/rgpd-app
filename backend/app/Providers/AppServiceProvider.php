<?php

namespace App\Providers;

use App\Services\Mail\FakeSender;
use App\Services\Mail\GmailSender;
use App\Services\Mail\MailSender;
use Illuminate\Http\Resources\Json\JsonResource;
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
    }

    public function boot(): void
    {
        // Les API Resources renvoient l'objet directement, sans l'envelopper dans { "data": ... }.
        JsonResource::withoutWrapping();
    }
}
