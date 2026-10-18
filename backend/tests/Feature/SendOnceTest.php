<?php

use App\Enums\RequestStatus;
use App\Jobs\SendErasureRequestJob;
use App\Models\ErasureRequest;
use App\Models\User;
use App\Services\Mail\MailSender;
use App\Services\Mail\MailSendingFailedException;

/*
 * Une lettre juridique ne doit jamais partir deux fois. Chaque test rejoue les essais du worker
 * (handle() appelé comme par la file) avec un Gmail qui tombe en panne à un moment précis.
 */

/**
 * Faux Gmail : les envois listés dans $pannes lèvent l'exception donnée au lieu de réussir.
 * Une RuntimeException imite une panne APRÈS l'acceptation par Gmail (base indisponible juste après,
 * worker arrêté…) ; une MailSendingFailedException, un refus de Gmail (rien n'est parti).
 *
 * @param  array<int, Throwable>  $pannes  numéro de l'appel (1, 2…) => exception
 */
function gmailEnPanne(array $pannes): MailSender
{
    $gmail = new class($pannes) implements MailSender
    {
        public int $appels = 0;

        /** @param  array<int, Throwable>  $pannes */
        public function __construct(private array $pannes) {}

        public function send(User $from, string $to, string $subject, string $body): string
        {
            $this->appels++;
            if (isset($this->pannes[$this->appels])) {
                throw $this->pannes[$this->appels];
            }

            return 'message-'.$this->appels;
        }
    };
    app()->instance(MailSender::class, $gmail);

    return $gmail;
}

function essaiDuWorker(ErasureRequest $request, string $kind = SendErasureRequestJob::INITIAL): void
{
    app()->call([new SendErasureRequestJob($request, $kind), 'handle']);
}

it('ne renvoie pas une lettre quand l\'essai précédent a planté après l\'appel à Gmail', function () {
    $gmail = gmailEnPanne([1 => new RuntimeException('base indisponible juste après l\'envoi')]);
    $request = ErasureRequest::factory()->for(verifiedUserWithGmail())->create();

    expect(fn () => essaiDuWorker($request))->toThrow(RuntimeException::class);
    essaiDuWorker($request);

    $request->refresh();
    expect($gmail->appels)->toBe(1)
        ->and($request->status)->toBe(RequestStatus::Draft)
        ->and($request->sending_started_at)->toBeNull()
        ->and($request->events->last()->payload)->toBe(['kind' => 'initial', 'reason' => 'uncertain']);
});

it('renvoie au prochain essai quand Gmail a refusé l\'envoi', function () {
    $gmail = gmailEnPanne([1 => new MailSendingFailedException('Erreur Gmail (HTTP 503).')]);
    $request = ErasureRequest::factory()->for(verifiedUserWithGmail())->create();

    expect(fn () => essaiDuWorker($request))->toThrow(MailSendingFailedException::class);
    essaiDuWorker($request);

    $request->refresh();
    expect($gmail->appels)->toBe(2)
        ->and($request->status)->toBe(RequestStatus::Sent)
        ->and($request->sending_started_at)->toBeNull()
        ->and($request->events->pluck('type.value')->all())->toBe(['sent']);
});

it('signale un envoi incertain quand le dernier essai a planté après l\'appel à Gmail', function () {
    gmailEnPanne([1 => new RuntimeException('worker arrêté')]);
    $request = ErasureRequest::factory()->for(verifiedUserWithGmail())->create();

    expect(fn () => essaiDuWorker($request))->toThrow(RuntimeException::class);
    (new SendErasureRequestJob($request))->failed(new RuntimeException('worker arrêté'));

    $request->refresh();
    expect($request->sending_started_at)->toBeNull()
        ->and($request->events->last()->payload)->toBe(['kind' => 'initial', 'reason' => 'uncertain']);
});

it('signale une erreur Gmail quand tous les essais ont été refusés', function () {
    gmailEnPanne([1 => new MailSendingFailedException('Gmail injoignable.')]);
    $request = ErasureRequest::factory()->for(verifiedUserWithGmail())->create();

    expect(fn () => essaiDuWorker($request))->toThrow(MailSendingFailedException::class);
    (new SendErasureRequestJob($request))->failed(new MailSendingFailedException('Gmail injoignable.'));

    expect($request->fresh()->events->last()->payload)->toBe(['kind' => 'initial', 'reason' => 'gmail_error']);
});

it('ne renvoie pas une relance quand l\'essai précédent a planté après l\'appel à Gmail', function () {
    $gmail = gmailEnPanne([1 => new RuntimeException('base indisponible juste après l\'envoi')]);
    $request = ErasureRequest::factory()->for(verifiedUserWithGmail())
        ->status(RequestStatus::Overdue, '-40 days', '-10 days')->create();

    expect(fn () => essaiDuWorker($request, SendErasureRequestJob::REMINDER))->toThrow(RuntimeException::class);
    essaiDuWorker($request, SendErasureRequestJob::REMINDER);

    $request->refresh();
    expect($gmail->appels)->toBe(1)
        ->and($request->events->pluck('type.value')->all())->not->toContain('reminder_sent')
        ->and($request->events->last()->payload)->toBe(['kind' => 'reminder', 'reason' => 'uncertain']);
});

it('après un envoi incertain, l\'utilisateur peut relancer l\'envoi lui-même', function () {
    $gmail = gmailEnPanne([1 => new RuntimeException('base indisponible juste après l\'envoi')]);
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->create();
    expect(fn () => essaiDuWorker($request))->toThrow(RuntimeException::class);
    essaiDuWorker($request);

    // Il a vérifié ses messages envoyés : la lettre n'était pas partie, il clique de nouveau sur « Envoyer ».
    $this->actingAs($user)->postJson("/api/requests/{$request->id}/send")->assertAccepted();

    expect($gmail->appels)->toBe(2)
        ->and($request->fresh()->status)->toBe(RequestStatus::Sent);
});
