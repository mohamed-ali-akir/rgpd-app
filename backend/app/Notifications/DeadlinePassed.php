<?php

namespace App\Notifications;

use App\Models\ErasureRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail envoyé à l'utilisateur (via le SMTP de l'app, pas via son Gmail)
 * quand le délai légal de réponse est dépassé.
 */
class DeadlinePassed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ErasureRequest $erasureRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->erasureRequest;

        return (new MailMessage)
            ->subject('Délai dépassé : '.$request->organization->name.' n\'a pas répondu')
            ->line('Le délai légal de réponse à votre demande auprès de '.$request->organization->name.' est dépassé.')
            ->line('Vous pouvez envoyer une relance, puis, sans réponse, préparer une plainte auprès de la CNIL.')
            ->action('Voir la demande', url('/requests/'.$request->id));
    }
}
