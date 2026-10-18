<?php

namespace App\Notifications;

use App\Models\ErasureRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * L'organisme a accepté l'effacement, mais les données sont toujours en ligne.
 */
class ErasureStillPresent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ErasureRequest $erasureRequest) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->erasureRequest->organization->name;

        return (new MailMessage)
            ->subject("Vos données sont toujours en ligne chez {$name}")
            ->line("{$name} a accepté votre demande d'effacement, mais nos vérifications successives montrent que vos données sont toujours publiées.")
            ->line('Vous pouvez relancer l\'organisme ou préparer une plainte auprès de la CNIL.')
            ->action('Voir la demande', url('/requests/'.$this->erasureRequest->id));
    }
}
