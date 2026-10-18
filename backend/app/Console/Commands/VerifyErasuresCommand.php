<?php

namespace App\Console\Commands;

use App\Jobs\VerifyErasureJob;
use App\Models\ErasureRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Tâche quotidienne : met en file une vérification pour chaque effacement accepté dont la date est venue.
 * La commande ne lit aucune page elle-même : le worker s'en charge, une demande à la fois.
 */
#[Signature('requests:verify-erasures')]
#[Description('Vérifie que les données ont bien disparu des pages dont l\'effacement a été accepté')]
class VerifyErasuresCommand extends Command
{
    public function handle(): int
    {
        $count = 0;
        ErasureRequest::query()->dueForVerification()->select('id')->lazyById()
            ->each(function (ErasureRequest $request) use (&$count) {
                VerifyErasureJob::dispatch($request);
                $count++;
            });

        $this->info("{$count} vérification(s) mise(s) en file.");

        return self::SUCCESS;
    }
}
