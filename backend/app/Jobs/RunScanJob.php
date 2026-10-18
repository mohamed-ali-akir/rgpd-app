<?php

namespace App\Jobs;

use App\Enums\ScanStatus;
use App\Models\Scan;
use App\Services\Scanner\Scanner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Exécute un scan dans le worker. Long par nature (sites lents) : délai maximal élevé, et pas de
 * nouvel essai automatique (le scan est marqué en échec, l'utilisateur peut en relancer un).
 *
 * Le worker doit tourner avec --timeout supérieur à $timeout, et la file "database" avec
 * DB_QUEUE_RETRY_AFTER supérieur aux deux (voir deploy/rgpd-queue.service).
 */
class RunScanJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public Scan $scan) {}

    public function uniqueId(): string
    {
        return (string) $this->scan->id;
    }

    public function handle(Scanner $scanner): void
    {
        $scan = $this->scan->fresh(['user.profile', 'user.googleAccount']);
        if ($scan !== null) {
            $scanner->run($scan);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $scan = $this->scan->fresh();
        if ($scan !== null && $scan->status->isActive()) {
            $scan->forceFill(['status' => ScanStatus::Failed, 'finished_at' => now()])->save();
        }
    }
}
