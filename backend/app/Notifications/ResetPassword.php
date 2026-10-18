<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail de réinitialisation du mot de passe. Le lien pointe vers la page React
 * /reset-password/{token}?email=..., qui renvoie ensuite le jeton à l'API.
 */
class ResetPassword extends Notification
{
    public function __construct(public string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->line('Vous recevez cet e-mail car une réinitialisation du mot de passe a été demandée pour votre compte.')
            ->action('Choisir un nouveau mot de passe', $this->resetUrl($notifiable))
            ->line("Ce lien expire dans {$minutes} minutes.")
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail : votre mot de passe reste inchangé.');
    }

    public function resetUrl(object $notifiable): string
    {
        // url() s'appuie sur APP_URL (ou l'hôte de la requête) : le front et l'API partagent le même domaine.
        return url('/reset-password/'.$this->token.'?email='.urlencode($notifiable->getEmailForPasswordReset()));
    }
}
