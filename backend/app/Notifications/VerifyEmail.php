<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmail extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Vérifiez votre adresse e-mail')
            ->line('Cliquez sur le bouton ci-dessous pour vérifier votre adresse e-mail.')
            ->action('Vérifier mon adresse', $this->verificationUrl($notifiable))
            ->line('Sans vérification, vous ne pourrez pas envoyer de demande depuis Gmail.');
    }

    /**
     * URL signée valable 1 heure. Le hash de l'e-mail empêche de réutiliser
     * le lien si l'adresse change entre-temps.
     */
    public function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'user' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);
    }
}
