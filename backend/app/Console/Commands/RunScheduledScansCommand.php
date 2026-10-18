<?php

namespace App\Console\Commands;

use App\Enums\ScanStatus;
use App\Enums\ScanTrigger;
use App\Jobs\RunScanJob;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tâche quotidienne : lance un scan pour chaque utilisateur qui a activé le scan mensuel et dont
 * le dernier scan date de plus de 30 jours. Tourner chaque jour (plutôt qu'une fois par mois)
 * rattrape automatiquement un jour où le serveur était arrêté.
 */
#[Signature('scans:run-scheduled')]
#[Description('Lance les scans mensuels automatiques des utilisateurs qui les ont activés')]
class RunScheduledScansCommand extends Command
{
    public function handle(): int
    {
        $since = now()->subDays((int) config('scanner.scan.auto_interval_days'));
        $count = 0;

        User::query()
            ->whereNotNull('email_verified_at')
            ->whereHas('profile', fn (Builder $q) => $q->where('auto_scan', true))
            // Aucun scan récent (manuel ou automatique), et aucun en cours.
            // Le OR est regroupé entre parenthèses : sans cela, il échapperait à la condition "scans de cet utilisateur".
            ->whereDoesntHave('scans', fn (Builder $q) => $q->where(
                fn (Builder $w) => $w->where('created_at', '>=', $since)->orWhereIn('status', ScanStatus::active()),
            ))
            ->select('id')
            ->lazyById()
            ->each(function (User $user) use (&$count) {
                RunScanJob::dispatch($user->scans()->create(['trigger' => ScanTrigger::Scheduled]));
                $count++;
            });

        $this->info("{$count} scan(s) automatique(s) lancé(s).");

        return self::SUCCESS;
    }
}
