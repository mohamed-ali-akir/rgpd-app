<?php

namespace App\Notifications;

use App\Models\Scan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Scan mensuel automatique terminé avec de nouveaux résultats. L'e-mail ne contient aucun résultat :
 * seulement leur nombre et un lien (les données restent dans l'application).
 */
class ScanCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Scan $scan) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->scan->new_findings_count;

        return (new MailMessage)
            ->subject($count > 1 ? "{$count} nouvelles pages vous concernent peut-être" : 'Une nouvelle page vous concerne peut-être')
            ->line('Le scan mensuel a trouvé de nouvelles pages qui semblent publier vos données personnelles.')
            ->line('Vérifiez-les : vous seul pouvez confirmer qu\'il s\'agit bien de vous.')
            ->action('Voir les résultats', url('/scanner'));
    }
}
