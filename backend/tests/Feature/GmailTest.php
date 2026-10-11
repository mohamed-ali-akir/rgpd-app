<?php

use App\Enums\RequestStatus;
use App\Http\Controllers\Api\GoogleAccountController;
use App\Models\ErasureRequest;
use App\Models\User;
use App\Services\Mail\GmailSender;
use App\Services\Mail\GoogleAccountDisconnectedException;
use App\Services\Mail\MailSender;
use App\Services\Mail\MailSendingFailedException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
 * Ces tests exécutent le VRAI code Gmail (GmailSender, GoogleAccountController…),
 * mais Http::fake() intercepte les appels HTTP : aucune requête ne part chez Google.
 * On vérifie ainsi ce que l'application envoie à Google et comment elle réagit à ses réponses.
 */

const TOKEN_URL = 'https://oauth2.googleapis.com/token';
const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

beforeEach(function () {
    config([
        'services.google.client_id' => 'client-id-de-test',
        'services.google.client_secret' => 'secret-de-test',
        'services.google.redirect' => 'http://localhost:5173/api/auth/google/callback',
    ]);
});

/** Décode le message MIME envoyé à l'API Gmail (base64 "URL-safe"). */
function sentMime(HttpRequest $request): string
{
    return base64_decode(strtr($request['raw'], '-_', '+/'));
}

function fakeGoogle(array $tokenResponse = ['access_token' => 'jeton-acces'], int $tokenStatus = 200, int $sendStatus = 200): void
{
    Http::fake([
        TOKEN_URL => Http::response($tokenResponse, $tokenStatus),
        SEND_URL => Http::response(['id' => 'message-gmail-42'], $sendStatus),
    ]);
}

describe('GmailSender', function () {
    it('échange le refresh token puis envoie un message MIME depuis l\'adresse Gmail', function () {
        fakeGoogle();
        $user = verifiedUserWithGmail();
        $user->profile->update(['first_name' => 'Camille', 'last_name' => 'Émond']);

        $id = (new GmailSender)->send($user, 'dpo@exemple.test', 'Effacement de données personnelles', "Madame, Monsieur,\nTexte accentué : é à ç.");

        expect($id)->toBe('message-gmail-42');

        Http::assertSent(fn (HttpRequest $r) => $r->url() === TOKEN_URL
            && $r['grant_type'] === 'refresh_token'
            && $r['refresh_token'] === 'fake-refresh-token'
            && $r['client_id'] === 'client-id-de-test');

        Http::assertSent(function (HttpRequest $r) {
            if ($r->url() !== SEND_URL) {
                return false;
            }
            $mime = sentMime($r);
            // Les en-têtes contenant des accents sont encodés selon la norme MIME (RFC 2047),
            // le corps en "quoted-printable" : on les décode avant de comparer.
            $headers = mb_decode_mimeheader(strstr($mime, "\r\n\r\n", true));

            return $r->hasHeader('Authorization', 'Bearer jeton-acces')
                && str_contains($mime, '=?utf-8?Q?donn=C3=A9es?=')
                && str_contains($headers, 'Subject: Effacement de données personnelles')
                && str_contains($headers, 'To: dpo@exemple.test')
                && str_contains($headers, 'From: Camille Émond <moi@gmail.test>')
                && str_contains($mime, 'Content-Type: text/plain; charset=utf-8')
                && str_contains(quoted_printable_decode($mime), 'Texte accentué : é à ç.');
        });
    });

    it('signale une déconnexion quand Google refuse le refresh token (invalid_grant)', function () {
        fakeGoogle(['error' => 'invalid_grant'], 400);

        (new GmailSender)->send(verifiedUserWithGmail(), 'dpo@exemple.test', 'Objet', 'Texte');
    })->throws(GoogleAccountDisconnectedException::class);

    it('signale une déconnexion quand Gmail répond 401 ou 403', function (int $status) {
        fakeGoogle(sendStatus: $status);

        (new GmailSender)->send(verifiedUserWithGmail(), 'dpo@exemple.test', 'Objet', 'Texte');
    })->with([401, 403])->throws(GoogleAccountDisconnectedException::class);

    it('lève une erreur temporaire (donc retentée) quand Gmail répond 500', function () {
        fakeGoogle(sendStatus: 500);

        (new GmailSender)->send(verifiedUserWithGmail(), 'dpo@exemple.test', 'Objet', 'Texte');
    })->throws(MailSendingFailedException::class);

    it('ne met aucune donnée personnelle dans les messages d\'erreur', function () {
        fakeGoogle(sendStatus: 500);

        try {
            (new GmailSender)->send(verifiedUserWithGmail(), 'dpo@exemple.test', 'Objet', 'Texte');
        } catch (MailSendingFailedException $e) {
            expect($e->getMessage())->toBe('Erreur Gmail (HTTP 500).');
        }
    });

    it('refuse d\'envoyer sans compte Gmail connecté', function () {
        Http::fake();

        (new GmailSender)->send(User::factory()->create(), 'dpo@exemple.test', 'Objet', 'Texte');
    })->throws(GoogleAccountDisconnectedException::class);
});

it('envoie une demande de bout en bout avec le vrai GmailSender', function () {
    fakeGoogle();
    $this->app->instance(MailSender::class, new GmailSender);
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->create();

    $this->actingAs($user)->postJson("/api/requests/{$request->id}/send")->assertAccepted();

    expect($request->fresh()->status)->toBe(RequestStatus::Sent)
        ->and($request->events()->first()->payload)->toBe(['channel' => 'gmail', 'message_id' => 'message-gmail-42']);
});

describe('Connexion OAuth (GoogleAccountController)', function () {
    it('redirige vers Google avec le seul scope gmail.send, en mode hors ligne', function () {
        $location = $this->actingAs(User::factory()->create())->get('/api/auth/google/redirect')
            ->assertRedirect()
            ->headers->get('Location');

        expect($location)->toStartWith('https://accounts.google.com/o/oauth2/auth')
            ->toContain(urlencode(GoogleAccountController::GMAIL_SEND_SCOPE))
            ->toContain('access_type=offline')
            ->toContain('prompt=consent')
            ->not->toContain('gmail.readonly');
    });

    it('enregistre le compte Gmail au retour de Google, avec un jeton chiffré', function () {
        Socialite::fake('google', SocialiteUser::fake([
            'email' => 'camille@gmail.test',
            'refreshToken' => 'refresh-token-google',
            'approvedScopes' => ['openid', 'email', GoogleAccountController::GMAIL_SEND_SCOPE],
        ]));
        $user = User::factory()->create();

        $this->actingAs($user)->get('/api/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect('/settings?google=connected');

        $account = $user->fresh()->googleAccount;
        expect($account->email)->toBe('camille@gmail.test')
            ->and($account->refresh_token)->toBe('refresh-token-google')
            ->and(DB::table('google_accounts')->value('refresh_token'))->not->toBe('refresh-token-google');
    });

    it('refuse une autorisation sans le scope gmail.send (case décochée chez Google)', function () {
        Socialite::fake('google', SocialiteUser::fake(['approvedScopes' => ['openid', 'email']]));
        $user = User::factory()->create();

        $this->actingAs($user)->get('/api/auth/google/callback?code=abc')
            ->assertRedirect('/settings?google=missing_scope');

        expect($user->fresh()->googleAccount)->toBeNull();
    });

    it('gère l\'annulation sur l\'écran de Google', function () {
        $this->actingAs(User::factory()->create())->get('/api/auth/google/callback?error=access_denied')
            ->assertRedirect('/settings?google=denied');
    });

    it('révoque le jeton chez Google à la déconnexion de Gmail', function () {
        config(['services.mail_sender' => 'gmail']);
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response()]);
        $user = verifiedUserWithGmail();

        $this->actingAs($user)->deleteJson('/api/google-account')->assertNoContent();

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://oauth2.googleapis.com/revoke' && $r['token'] === 'fake-refresh-token');
        expect($user->fresh()->googleAccount)->toBeNull();
    });
});
