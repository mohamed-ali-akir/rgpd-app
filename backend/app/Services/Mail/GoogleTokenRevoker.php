<?php

namespace App\Services\Mail;

use App\Models\GoogleAccount;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Révoque l'autorisation Gmail côté Google (déconnexion ou suppression du compte).
 * Supprimer le jeton de notre base ne suffit pas : il resterait valable chez Google.
 */
class GoogleTokenRevoker
{
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /**
     * @return bool true si Google a confirmé la révocation
     */
    public function revoke(GoogleAccount $account): bool
    {
        if (config('services.mail_sender') !== 'gmail') {
            return true; // en dev/tests, aucun vrai jeton n'existe
        }

        try {
            return Http::asForm()->timeout(10)
                ->post(self::REVOKE_URL, ['token' => $account->refresh_token])
                ->successful();
        } catch (Throwable) {
            // On ne bloque jamais une suppression de compte pour une panne chez Google.
            return false;
        }
    }
}
