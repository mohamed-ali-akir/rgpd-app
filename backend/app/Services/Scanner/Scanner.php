<?php

namespace App\Services\Scanner;

use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use App\Models\Scan;
use App\Notifications\ScanCompleted;
use App\Services\Identity\Identity;
use App\Services\Scanner\Sources\ScanSource;
use App\Services\Scanner\Sources\SourceSkippedException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Exécute un scan : interroge chaque source, enregistre les résultats, rédige le rapport.
 *
 * Robustesse : chaque source est isolée. Une source en panne est notée "failed" dans le rapport
 * et les autres continuent ; le scan n'est en échec que si TOUTES les sources utilisées ont échoué.
 */
class Scanner
{
    /**
     * @param  iterable<ScanSource>  $sources  sources déclarées dans AppServiceProvider
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly FindingRecorder $recorder,
    ) {}

    public function run(Scan $scan): void
    {
        // Idempotence : un scan déjà terminé (job rejoué) n'est pas relancé.
        if (! $scan->status->isActive()) {
            return;
        }

        $scan->forceFill(['status' => ScanStatus::Running, 'started_at' => now()])->save();

        $identity = Identity::forUser($scan->user);
        $report = [];
        $candidates = [];

        foreach ($this->sources as $source) {
            $key = $source->key()->value;
            try {
                $found = $source->scan($identity);
                $candidates = [...$candidates, ...$found];
                $report[$key] = ['status' => 'ok', 'found' => count($found)];
            } catch (SourceSkippedException) {
                $report[$key] = ['status' => 'skipped'];
            } catch (Throwable $e) {
                // Journal : la source et le type d'erreur seulement, jamais l'identité recherchée.
                Log::warning('Source du scanner en échec', ['source' => $key, 'error' => $e::class, 'scan' => $scan->id]);
                $report[$key] = ['status' => 'failed', 'error' => class_basename($e)];
            }
        }

        $new = $this->recorder->record($scan->user, $scan, $candidates);

        $used = array_filter($report, fn (array $r) => $r['status'] !== 'skipped');
        $allFailed = $used !== [] && array_filter($used, fn (array $r) => $r['status'] === 'ok') === [];

        $scan->forceFill([
            'status' => $allFailed ? ScanStatus::Failed : ScanStatus::Completed,
            'report' => $report,
            'new_findings_count' => $new,
            'finished_at' => now(),
        ])->save();

        // Scan automatique : l'utilisateur n'est pas devant l'écran, on le prévient s'il y a du nouveau.
        if ($scan->trigger === ScanTrigger::Scheduled && $new > 0) {
            $scan->user->notify(new ScanCompleted($scan));
        }
    }
}
