<?php

use Illuminate\Support\Facades\Schedule;

// Le serveur lance "php artisan schedule:run" chaque minute (une ligne de cron),
// et Laravel exécute les tâches ci-dessous au bon moment.

// Vérification quotidienne des délais légaux (art. 12.3).
// L'application tourne en UTC : on précise le fuseau pour que "8 h" soit 8 h à Paris.
Schedule::command('requests:check-deadlines')->dailyAt('08:00')->timezone(config('app.display_timezone'))->withoutOverlapping();

// Vérification que les effacements acceptés ont vraiment eu lieu (lecture des pages par le worker).
Schedule::command('requests:verify-erasures')->dailyAt('09:00')->timezone(config('app.display_timezone'))->withoutOverlapping();

// Scans mensuels automatiques (utilisateurs qui les ont activés dans leur profil).
Schedule::command('scans:run-scheduled')->dailyAt('10:00')->timezone(config('app.display_timezone'))->withoutOverlapping();

// Mode démo : le compte de démonstration repart d'un état propre chaque nuit.
if (config('app.demo')) {
    Schedule::command('demo:reset')->dailyAt('03:00')->timezone(config('app.display_timezone'));
}

// Conservation limitée : suppression des demandes clôturées depuis plus de 12 mois
// (voir ErasureRequest::prunable()).
Schedule::command('model:prune')->daily();

// Supprime les jetons de réinitialisation de mot de passe expirés (ils contiennent l'adresse e-mail).
Schedule::command('auth:clear-resets')->daily();
