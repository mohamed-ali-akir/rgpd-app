<?php

use App\Enums\DataType;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Enums\OrganizationCategory;
use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use App\Jobs\RunScanJob;
use App\Models\ErasureRequest;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use App\Services\Web\UrlNormalizer;
use Illuminate\Support\Facades\Queue;

function finding(User $user, array $attributes = []): Finding
{
    static $n = 0;
    $n++;
    $url = $attributes['url'] ?? "https://annuaire.exemple.fr/fiche/{$n}";
    $finding = new Finding([
        'source' => FindingSource::WebSearch,
        'url' => $url,
        'url_hash' => app(UrlNormalizer::class)->hash($url),
        'domain' => 'annuaire.exemple.fr',
        'organization_id' => $attributes['organization_id'] ?? null,
        'title' => 'Fiche',
        'snippet' => 'Camille Martin',
        'matched' => $attributes['matched'] ?? [DataType::Name, DataType::Phone],
        'confidence' => 80,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);
    $finding->user_id = $user->id;
    $finding->status = $attributes['status'] ?? FindingStatus::New;
    $finding->save();

    return $finding;
}

describe('POST /api/scans', function () {
    it('met un scan en file', function () {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/scans')
            ->assertAccepted()
            ->assertJson(['status' => 'queued', 'trigger' => 'manual']);

        Queue::assertPushed(RunScanJob::class, fn ($job) => $job->scan->user_id === $user->id);
    });

    it('exige un e-mail vérifié', function () {
        $this->actingAs(User::factory()->unverified()->create())->postJson('/api/scans')->assertForbidden();
    });

    it('refuse un second scan pendant qu\'un autre tourne', function () {
        Queue::fake();
        $user = User::factory()->create();
        $user->scans()->create(['trigger' => ScanTrigger::Manual, 'status' => ScanStatus::Running]);

        $this->actingAs($user)->postJson('/api/scans')->assertConflict();
    });

    it('limite à 4 scans manuels par 30 jours', function () {
        Queue::fake();
        $user = User::factory()->create();
        foreach (range(1, 4) as $i) {
            $user->scans()->create(['trigger' => ScanTrigger::Manual, 'status' => ScanStatus::Completed]);
        }

        $this->actingAs($user)->postJson('/api/scans')
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Limite de 4 recherches par mois atteinte.');
    });
});

it('ne montre que mes scans et mes résultats', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    finding($me);
    finding($other);
    $otherScan = $other->scans()->create(['trigger' => ScanTrigger::Manual]);

    $this->actingAs($me)->getJson('/api/findings')->assertOk()->assertJsonCount(1);
    $this->actingAs($me)->getJson("/api/scans/{$otherScan->id}")->assertForbidden();
});

it('enregistre la décision de l\'utilisateur', function () {
    $user = User::factory()->create();
    $finding = finding($user);

    $this->actingAs($user)->patchJson("/api/findings/{$finding->id}", ['status' => 'rejected'])
        ->assertOk()
        ->assertJson(['status' => 'rejected']);

    expect($finding->fresh()->decided_at)->not->toBeNull();
});

it('interdit de décider pour le résultat d\'un autre', function () {
    $finding = finding(User::factory()->create());

    $this->actingAs(User::factory()->create())->patchJson("/api/findings/{$finding->id}", ['status' => 'confirmed'])->assertForbidden();
});

describe('POST /api/findings/requests', function () {
    it('crée un brouillon par résultat confirmé, avec les données détectées', function () {
        $user = User::factory()->create();
        $organization = organization(['domain' => 'annuaire.exemple.fr']);
        $finding = finding($user, ['status' => FindingStatus::Confirmed, 'organization_id' => $organization->id]);

        $response = $this->actingAs($user)->postJson('/api/findings/requests', ['finding_ids' => [$finding->id]])
            ->assertCreated();

        $request = ErasureRequest::sole();
        expect($response->json('created'))->toBe([['finding_id' => $finding->id, 'request_id' => $request->id]])
            ->and($request->status->value)->toBe('draft')
            ->and($request->url)->toBe($finding->url)
            ->and($request->data_types->map->value->all())->toBe(['name', 'phone'])
            ->and($request->events()->first()->payload)->toMatchArray(['finding_id' => $finding->id, 'own_data_confirmed' => true])
            ->and($finding->fresh()->erasure_request_id)->toBe($request->id);
    });

    it('est idempotente : un second appel ne crée rien', function () {
        $user = User::factory()->create();
        $finding = finding($user, ['status' => FindingStatus::Confirmed, 'organization_id' => organization()->id]);

        $this->actingAs($user)->postJson('/api/findings/requests', ['finding_ids' => [$finding->id]])->assertCreated();
        $this->actingAs($user)->postJson('/api/findings/requests', ['finding_ids' => [$finding->id]])
            ->assertOk()
            ->assertJsonPath('skipped.0.reason', 'already_created');

        expect(ErasureRequest::count())->toBe(1);
    });

    it('explique pourquoi un résultat n\'a pas donné de demande', function () {
        $user = User::factory()->create();
        $notConfirmed = finding($user);
        $unknownOrg = finding($user, ['status' => FindingStatus::Confirmed]);
        $othersFinding = finding(User::factory()->create(), ['status' => FindingStatus::Confirmed]);

        $response = $this->actingAs($user)->postJson('/api/findings/requests', [
            'finding_ids' => [$notConfirmed->id, $unknownOrg->id, $othersFinding->id],
        ])->assertOk();

        expect(collect($response->json('skipped'))->pluck('reason', 'finding_id')->all())->toBe([
            $othersFinding->id => 'not_found',
            $notConfirmed->id => 'not_confirmed',
            $unknownOrg->id => 'unknown_organization',
        ]);
        expect(ErasureRequest::count())->toBe(0);
    });

    it('reconnaît un organisme ajouté après le scan', function () {
        $user = User::factory()->create();
        $finding = finding($user, ['status' => FindingStatus::Confirmed]);
        organization(['domain' => 'annuaire.exemple.fr']);

        $this->actingAs($user)->postJson('/api/findings/requests', ['finding_ids' => [$finding->id]])->assertCreated();
    });

    it('relie le résultat à une demande déjà ouverte pour la même page, sans doublon', function () {
        $user = User::factory()->create();
        $organization = organization(['domain' => 'annuaire.exemple.fr']);
        $existing = ErasureRequest::factory()->for($user)->create(['url' => 'https://annuaire.exemple.fr/camille/', 'organization_id' => $organization->id]);
        $finding = finding($user, ['url' => 'https://annuaire.exemple.fr/camille', 'status' => FindingStatus::Confirmed, 'organization_id' => $organization->id]);

        $this->actingAs($user)->postJson('/api/findings/requests', ['finding_ids' => [$finding->id]])
            ->assertOk()
            ->assertJsonPath('skipped.0.reason', 'duplicate_request')
            ->assertJsonPath('skipped.0.request_id', $existing->id);

        expect(ErasureRequest::count())->toBe(1)->and($finding->fresh()->erasure_request_id)->toBe($existing->id);
    });
});

it('propose des vérifications guidées sans contacter les sites', function () {
    organization(['name' => 'LinkedIn', 'domain' => 'linkedin.com', 'category' => OrganizationCategory::SocialNetwork]);
    organization(['name' => 'Le Monde', 'domain' => 'lemonde.fr', 'category' => OrganizationCategory::Press]);
    $user = User::factory()->create();
    $user->profile->update(['first_name' => 'Camille', 'last_name' => 'Martin']);
    // Ajouté par un utilisateur : pas dans la liste de référence, donc jamais proposé aux autres.
    organization(['name' => 'Annuaire ajouté', 'domain' => 'ajoute.fr', 'category' => OrganizationCategory::Directory, 'created_by' => User::factory()->create()->id]);

    $this->actingAs($user)->getJson('/api/guided-checks')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.organization.domain', 'linkedin.com')
        ->assertJsonPath('0.search_url', 'https://duckduckgo.com/?q=%22Camille%20Martin%22%20site%3Alinkedin.com');
});

it('supprime scans et résultats avec le compte, et les inclut dans l\'export', function () {
    $user = User::factory()->create();
    finding($user);
    $user->scans()->create(['trigger' => ScanTrigger::Manual]);

    $this->actingAs($user)->get('/api/me/export')->assertOk()->assertJsonCount(1, 'findings')->assertJsonCount(1, 'scans');
    fromSpa()->actingAs($user)->deleteJson('/api/me', ['password' => 'password'])->assertNoContent();

    expect(Finding::count())->toBe(0)->and(Scan::count())->toBe(0);
});

it('active le scan automatique depuis le profil', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/profile', ['first_name' => 'Camille', 'last_name' => 'Martin', 'auto_scan' => true])
        ->assertOk()
        ->assertJson(['auto_scan' => true]);
});
