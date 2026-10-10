<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Models\ErasureRequest;
use App\Notifications\DeadlinePassed;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Exécutée chaque jour (routes/console.php) : les demandes "sent" ou "extended"
 * dont l'échéance est dépassée passent en "overdue", et l'utilisateur est prévenu.
 */
#[Signature('requests:check-deadlines')]
#[Description('Passe en "délai dépassé" les demandes sans réponse après l\'échéance légale')]
class CheckDeadlinesCommand extends Command
{
    public function handle(): int
    {
        $count = 0;

        // lazyById : charge les demandes par paquets, sans tout mettre en mémoire.
        ErasureRequest::query()->pastDue()->with('user', 'organization')->lazyById()
            ->each(function (ErasureRequest $request) use (&$count) {
                $request->transitionTo(RequestStatus::Overdue, [], ['due_at' => $request->due_at->toIso8601String()]);
                $request->user->notify(new DeadlinePassed($request));
                $count++;
            });

        // Pas de donnée personnelle dans la sortie (elle finit dans les logs du cron).
        $this->info("{$count} demande(s) passée(s) en délai dépassé.");

        return self::SUCCESS;
    }
}
