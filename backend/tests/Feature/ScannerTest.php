<?php

use App\Enums\DataType;
use App\Enums\FindingSource;
use App\Enums\FindingStatus;
use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use App\Jobs\RunScanJob;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use App\Notifications\ScanCompleted;
use App\Services\Identity\Identity;
use App\Services\Scanner\Candidate;
use App\Services\Scanner\FindingRecorder;
use App\Services\Scanner\Scanner;
use App\Services\Scanner\Sources\ScanSource;
use App\Services\Scanner\Sources\SourceSkippedException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * Source de test : renvoie des résultats fixés, ou lève l'exception donnée.
 *
 * @param  list<Candidate>|Throwable  $behaviour
 */
function stubSource(FindingSource $key, array|Throwable $behaviour): ScanSource
{
    return new class($key, $behaviour) implements ScanSource
    {
        public function __construct(private FindingSource $source, private array|Throwable $behaviour) {}

        public function key(): FindingSource
        {
            return $this->source;
        }

        public function scan(Identity $identity): array
        {
            if ($this->behaviour instanceof Throwable) {
                throw $this->behaviour;
            }

            return $this->behaviour;
        }
    };
}

function candidate(string $url, array $types = [DataType::Name], int $confidence = 50): Candidate
{
    return new Candidate(FindingSource::WebSearch, $url, 'Titre', 'Extrait', $types, $confidence, ['verified' => true]);
}

function runScan(User $user, array $sources, ScanTrigger $trigger = ScanTrigger::Manual): Scan
{
    $scan = $user->scans()->create(['trigger' => $trigger]);
    (new Scanner($sources, app(FindingRecorder::class)))->run($scan);

    return $scan->fresh();
}

it('isole une source en panne : le scan aboutit avec les autres', function () {
    $user = User::factory()->create();

    $scan = runScan($user, [
        stubSource(FindingSource::CompanyRegistry, new RuntimeException('API en panne')),
        stubSource(FindingSource::WebSearch, [candidate('https://annuaire.exemple.fr/camille')]),
    ]);

    expect($scan->status)->toBe(ScanStatus::Completed)
        ->and($scan->report)->toBe([
            'company_registry' => ['status' => 'failed', 'error' => 'RuntimeException'],
            'web_search' => ['status' => 'ok', 'found' => 1],
        ])
        ->and($scan->new_findings_count)->toBe(1)
        ->and($user->findings()->count())->toBe(1);
});

it('échoue seulement si toutes les sources utilisées échouent', function () {
    $scan = runScan(User::factory()->create(), [
        stubSource(FindingSource::CompanyRegistry, new RuntimeException('panne')),
        stubSource(FindingSource::WebSearch, new SourceSkippedException('non configuré')),
    ]);

    expect($scan->status)->toBe(ScanStatus::Failed)
        ->and($scan->report['web_search'])->toBe(['status' => 'skipped']);
});

it('reconnaît l\'organisme et chiffre les données du résultat', function () {
    $organization = organization(['domain' => 'annuaire.exemple.fr']);
    $user = User::factory()->create();

    runScan($user, [stubSource(FindingSource::WebSearch, [candidate('https://www.annuaire.exemple.fr/camille')])]);

    $finding = $user->findings()->first();
    expect($finding->organization_id)->toBe($organization->id)
        ->and($finding->domain)->toBe('annuaire.exemple.fr')
        ->and(DB::table('findings')->value('title'))->not->toBe('Titre');
});

it('ne recrée pas un résultat déjà connu et garde la décision de l\'utilisateur', function () {
    $user = User::factory()->create();
    runScan($user, [stubSource(FindingSource::WebSearch, [candidate('https://annuaire.exemple.fr/camille')])]);
    $user->findings()->first()->decide(FindingStatus::Rejected);

    $second = runScan($user, [stubSource(FindingSource::WebSearch, [
        candidate('https://annuaire.exemple.fr/camille/?utm_source=x', [DataType::Phone], 80),
    ])]);

    $finding = $user->findings()->sole();
    expect($second->new_findings_count)->toBe(0)
        ->and($finding->status)->toBe(FindingStatus::Rejected)
        ->and($finding->confidence)->toBe(80)
        ->and($finding->matched->map->value->all())->toBe(['name', 'phone'])
        ->and($finding->scan_id)->toBe($second->id);
});

it('fusionne une même page trouvée par deux sources', function () {
    $user = User::factory()->create();

    $scan = runScan($user, [
        stubSource(FindingSource::CompanyRegistry, [new Candidate(FindingSource::CompanyRegistry, 'https://exemple.fr/p', 'A', 'a', [DataType::BirthDate], 60)]),
        stubSource(FindingSource::WebSearch, [candidate('https://exemple.fr/p', [DataType::Name], 40)]),
    ]);

    $finding = $user->findings()->sole();
    expect($scan->new_findings_count)->toBe(1)
        ->and($finding->source)->toBe(FindingSource::CompanyRegistry)
        ->and($finding->matched->map->value->all())->toEqualCanonicalizing(['birth_date', 'name']);
});

it('ne relance pas un scan déjà terminé (job rejoué)', function () {
    $user = User::factory()->create();
    $scan = runScan($user, [stubSource(FindingSource::WebSearch, [candidate('https://exemple.fr/1')])]);

    (new Scanner([stubSource(FindingSource::WebSearch, [candidate('https://exemple.fr/2')])], app(FindingRecorder::class)))->run($scan);

    expect($user->findings()->count())->toBe(1);
});

it('prévient l\'utilisateur après un scan automatique avec du nouveau, sans détail dans l\'e-mail', function () {
    Notification::fake();
    $user = User::factory()->create();

    runScan($user, [stubSource(FindingSource::WebSearch, [candidate('https://exemple.fr/1')])], ScanTrigger::Scheduled);
    runScan(User::factory()->create(), [stubSource(FindingSource::WebSearch, [candidate('https://exemple.fr/2')])], ScanTrigger::Manual);

    Notification::assertSentTo($user, ScanCompleted::class);
    Notification::assertSentTimes(ScanCompleted::class, 1);
});

it('marque le scan en échec si le job plante', function () {
    $scan = User::factory()->create()->scans()->create(['trigger' => ScanTrigger::Manual]);

    (new RunScanJob($scan))->failed(new RuntimeException('timeout'));

    expect($scan->fresh()->status)->toBe(ScanStatus::Failed);
});

it('lance les scans automatiques des utilisateurs volontaires, au plus une fois par mois', function () {
    Queue::fake();
    $volunteer = User::factory()->create();
    $volunteer->profile->update(['auto_scan' => true]);
    $recent = User::factory()->create();
    $recent->profile->update(['auto_scan' => true]);
    $recent->scans()->create(['trigger' => ScanTrigger::Manual, 'status' => ScanStatus::Completed]);
    User::factory()->create(); // n'a pas activé l'option
    $unverified = User::factory()->unverified()->create();
    $unverified->profile->update(['auto_scan' => true]);

    $this->artisan('scans:run-scheduled')->assertSuccessful();

    Queue::assertPushed(RunScanJob::class, 1);
    expect($volunteer->scans()->sole()->trigger)->toBe(ScanTrigger::Scheduled);
});

it('purge les résultats écartés après 6 mois, pas les autres', function () {
    $user = User::factory()->create();
    runScan($user, [stubSource(FindingSource::WebSearch, [candidate('https://exemple.fr/1'), candidate('https://exemple.fr/2')])]);
    [$old, $kept] = $user->findings()->orderBy('id')->get()->all();
    $old->decide(FindingStatus::Rejected);
    Finding::whereKey($old->id)->toBase()->update(['decided_at' => now()->subMonths(7)]);
    $kept->decide(FindingStatus::Confirmed);

    $this->artisan('model:prune', ['--model' => [Finding::class]]);

    expect(Finding::find($old->id))->toBeNull()->and(Finding::find($kept->id))->not->toBeNull();
});
