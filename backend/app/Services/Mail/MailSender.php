<?php

namespace App\Services\Mail;

use App\Models\User;

/**
 * Envoie une lettre AU NOM de l'utilisateur, depuis sa propre adresse.
 * Deux implémentations : GmailSender (réelle) et FakeSender (tests et dev).
 * Le choix se fait dans AppServiceProvider selon config('services.mail_sender').
 */
interface MailSender
{
    /**
     * @return string identifiant du message envoyé (conservé dans l'historique)
     *
     * @throws GoogleAccountDisconnectedException si l'utilisateur a révoqué l'accès
     * @throws MailSendingFailedException pour toute autre erreur d'envoi
     */
    public function send(User $from, string $to, string $subject, string $body): string;
}
