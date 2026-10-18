<?php

use App\Jobs\RunScanJob;
use App\Jobs\SendErasureRequestJob;
use App\Jobs\VerifyErasureJob;

/*
 * Invariant de la file d'attente : un job qui dure plus longtemps que "retry_after" est considéré
 * comme perdu et relancé par un autre worker (scan lancé deux fois, e-mail envoyé deux fois).
 * Ce test casse si quelqu'un allonge un job sans ajuster la configuration.
 */
it('laisse à chaque job le temps de finir avant d\'être considéré comme perdu', function (string $job) {
    $timeout = (new ReflectionClass($job))->getDefaultProperties()['timeout'] ?? 60;

    expect(config('queue.connections.database.retry_after'))->toBeGreaterThan($timeout);
})->with([RunScanJob::class, VerifyErasureJob::class, SendErasureRequestJob::class]);
