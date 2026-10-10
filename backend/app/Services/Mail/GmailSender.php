<?php

namespace App\Services\Mail;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoi via l'API Gmail (scope gmail.send), depuis l'adresse de l'utilisateur.
 *
 * Socialite ne sert qu'à la connexion OAuth (GoogleAccountController). Ici on appelle
 * directement deux points d'API Google avec le client HTTP de Laravel :
 *   1. oauth2.googleapis.com/token : échange le refresh token contre un jeton d'accès (1 h)
 *   2. gmail.googleapis.com/.../messages/send : envoie le message au format MIME
 */
class GmailSender implements MailSender
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public function send(User $from, string $to, string $subject, string $body): string
    {
        $account = $from->googleAccount;
        if ($account === null) {
            throw new GoogleAccountDisconnectedException('Aucun compte Gmail connecté.');
        }

        $accessToken = $this->accessToken($account->refresh_token);

        // Symfony Mime (déjà installé avec Laravel) construit un message MIME valide
        // (encodage des accents dans l'objet, UTF-8, etc.).
        $email = (new Email)
            ->from(new Address($account->email, $from->profile?->fullName() ?? ''))
            ->to($to)
            ->subject($subject)
            ->text($body);

        try {
            $response = Http::withToken($accessToken)->timeout(20)->post(self::SEND_URL, [
                'raw' => $this->base64UrlEncode($email->toString()),
            ]);
        } catch (ConnectionException) {
            throw new MailSendingFailedException('Gmail injoignable.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new GoogleAccountDisconnectedException('Gmail a refusé l\'envoi (accès révoqué ou scope manquant).');
        }
        if ($response->failed()) {
            throw new MailSendingFailedException('Erreur Gmail (HTTP '.$response->status().').');
        }

        return (string) $response->json('id');
    }

    private function accessToken(string $refreshToken): string
    {
        try {
            $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);
        } catch (ConnectionException) {
            throw new MailSendingFailedException('Google injoignable.');
        }

        // "invalid_grant" : le refresh token n'est plus valable, l'utilisateur doit reconnecter Gmail.
        if ($response->json('error') === 'invalid_grant') {
            throw new GoogleAccountDisconnectedException('Le refresh token Google n\'est plus valable.');
        }
        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new MailSendingFailedException('Impossible d\'obtenir un jeton d\'accès Google (HTTP '.$response->status().').');
        }

        return $response->json('access_token');
    }

    /**
     * L'API Gmail attend du base64 "URL-safe" : + et / remplacés, sans "=" final.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
