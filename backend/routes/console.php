<?php

use Illuminate\Support\Facades\Schedule;

// Le serveur lance "php artisan schedule:run" chaque minute (une ligne de cron),
// et Laravel exécute les tâches ci-dessous au bon moment.

// Vérification quotidienne des délais légaux (art. 12.3).
Schedule::command('requests:check-deadlines')->dailyAt('08:00')->withoutOverlapping();

// Conservation limitée : suppression des demandes clôturées depuis plus de 12 mois
// (voir ErasureRequest::prunable()).
Schedule::command('model:prune')->daily();
