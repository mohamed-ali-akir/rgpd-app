<?php

use App\Enums\RequestStatus;
use App\Models\ErasureRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\Mail\FakeSender;
use App\Services\Mail\MailSender;
use App\Services\Scanner\Scanner;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['app.demo' => true]);
});

it('expose le mode démo et ses identifiants au front', function () {
    $this->getJson('/api/meta')->assertOk()->assertExactJson([
        'demo' => true,
        'demo_credentials' => ['email' => 'demo@example.test', 'password' => 'demo-efface-moi'],
    ]);
});

it('n\'expose aucun identifiant hors démo', function () {
    config(['app.demo' => false]);

    $this->getJson('/api/meta')->assertOk()->assertExactJson(['demo' => false, 'demo_credentials' => null]);
});

it('bloque l\'inscription, le mot de passe oublié, Gmail et la suppression du compte', function (string $method, string $uri) {
    $user = User::factory()->create();

    fromSpa()->actingAs($user)->json($method, $uri, ['password' => 'password', 'email' => 'x@exemple.test'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Indisponible dans la version de démonstration.');
})->with([
    'inscription' => ['POST', '/api/register'],
    'mot de passe oublié' => ['POST', '/api/forgot-password'],
    'connexion Gmail' => ['GET', '/api/auth/google/redirect'],
    'déconnexion Gmail' => ['DELETE', '/api/google-account'],
    'suppression du compte' => ['DELETE', '/api/me'],
]);

it('n\'envoie jamais réellement, même si Gmail est configuré', function () {
    config(['services.mail_sender' => 'gmail']);
    app()->forgetInstance(MailSender::class);

    expect(app(MailSender::class))->toBeInstanceOf(FakeSender::class);
});

it('scanne uniquement des données fictives, sans réseau', function () {
    Http::fake();
    $user = User::factory()->create();
    $scan = $user->scans()->create(['trigger' => 'manual']);

    app(Scanner::class)->run($scan);

    expect($user->findings()->count())->toBe(4)
        ->and($user->findings()->pluck('domain')->every(fn ($d) => str_ends_with($d, '.example')))->toBeTrue();
    Http::assertNothingSent();
});

it('réinitialise la démo dans un état complet et reproductible', function () {
    Http::fake();

    $this->artisan('demo:reset')->assertSuccessful();
    // Contenu laissé par un visiteur, puis nouvelle réinitialisation.
    $demo = User::where('email', 'demo@example.test')->sole();
    ErasureRequest::factory()->for($demo)->create();
    Organization::factory()->create(['created_by' => $demo->id, 'name' => 'Ajout d\'un visiteur']);
    $this->artisan('demo:reset')->assertSuccessful();

    $demo->refresh();
    $statuses = $demo->erasureRequests()->pluck('status')->map->value->sort()->values()->all();
    expect($statuses)->toBe(['accepted', 'draft', 'overdue', 'sent', 'sent'])
        ->and($demo->erasureRequests()->where('kind', 'access')->count())->toBe(1)
        ->and($demo->findings()->count())->toBe(4)
        ->and($demo->hasVerifiedEmail())->toBeTrue()
        ->and(Organization::where('name', 'Ajout d\'un visiteur')->exists())->toBeFalse();

    // Connexion avec les identifiants affichés sur la page de connexion.
    fromSpa()->postJson('/api/login', ['email' => 'demo@example.test', 'password' => 'demo-efface-moi'])->assertOk();
});

it('envoie une demande de démo sans rien envoyer pour de vrai', function () {
    Http::fake();
    $this->artisan('demo:reset')->assertSuccessful();
    $demo = User::where('email', 'demo@example.test')->sole();
    $draft = $demo->erasureRequests()->where('status', 'draft')->sole();
    $draft->update(['channel' => 'gmail']);

    $this->actingAs($demo)->postJson("/api/requests/{$draft->id}/send")->assertAccepted();

    expect($draft->fresh()->status)->toBe(RequestStatus::Sent);
    Http::assertNothingSent();
});

it('refuse de réinitialiser hors mode démo sans --force', function () {
    config(['app.demo' => false]);

    $this->artisan('demo:reset')->assertFailed();
});
