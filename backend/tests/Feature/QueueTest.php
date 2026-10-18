<?php

use App\Jobs\SendErasureRequestJob;
use App\Jobs\VerifyErasureJob;
use App\Models\ErasureRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * Les autres tests utilisent la file "sync" : le job s'exécute tout de suite, avec le modèle
 * encore en mémoire. Ici on passe par la vraie file "database", comme en production :
 * le job ne stocke que l'id du modèle, et le worker le relit plus tard.
 */

beforeEach(function () {
    config(['queue.default' => 'database']);
});

function traiterLaFile(): void
{
    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
}

it('abandonne sans erreur l\'envoi d\'une demande supprimée avant le passage du worker', function () {
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->create();

    SendErasureRequestJob::dispatch($request);
    $request->delete();
    traiterLaFile();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(fakeSender()->sent)->toBe([]);
});

it('abandonne sans erreur les jobs d\'un compte supprimé avant le passage du worker', function () {
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->create();

    SendErasureRequestJob::dispatch($request);
    VerifyErasureJob::dispatch($request);
    // Suppression du compte : la demande disparaît en cascade (clé étrangère).
    $user->delete();
    traiterLaFile();
    traiterLaFile();

    expect(ErasureRequest::count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(fakeSender()->sent)->toBe([]);
});

it('envoie normalement une demande toujours présente quand le worker passe', function () {
    $user = verifiedUserWithGmail();
    $request = ErasureRequest::factory()->for($user)->create();

    SendErasureRequestJob::dispatch($request);
    traiterLaFile();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(fakeSender()->sent)->toHaveCount(1);
});
