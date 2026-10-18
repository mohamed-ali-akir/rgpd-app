<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mail\GoogleTokenRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use LogicException;
use Throwable;

/**
 * Connexion Gmail via OAuth (Socialite). On ne demande QUE le scope gmail.send :
 * l'app peut envoyer des e-mails, mais ne peut pas lire la boîte de réception.
 */
class GoogleAccountController extends Controller
{
    public const GMAIL_SEND_SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    public function redirect(): RedirectResponse
    {
        $provider = Socialite::driver('google');
        // Socialite renvoie un type générique : on vérifie qu'il s'agit bien d'un fournisseur OAuth 2.
        if (! $provider instanceof AbstractProvider) {
            throw new LogicException('Le fournisseur Google configuré n\'est pas un fournisseur OAuth 2.');
        }

        return $provider
            ->scopes([self::GMAIL_SEND_SCOPE])
            // offline : Google renvoie un refresh token (pour envoyer plus tard, ex. une relance).
            // consent : force l'écran d'accord, sinon Google ne renvoie le refresh token qu'une fois.
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        // L'utilisateur a cliqué sur "Annuler" chez Google.
        if ($request->has('error')) {
            return redirect('/settings?google=denied');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect('/settings?google=error');
        }
        if (! $googleUser instanceof GoogleUser) {
            return redirect('/settings?google=error');
        }

        // Google permet de décocher un scope sur l'écran d'accord : on vérifie qu'on a bien gmail.send.
        $scopes = $googleUser->approvedScopes;
        if (! in_array(self::GMAIL_SEND_SCOPE, $scopes, true) || ! $googleUser->refreshToken) {
            return redirect('/settings?google=missing_scope');
        }

        $request->user()->googleAccount()->updateOrCreate([], [
            'email' => $googleUser->getEmail(),
            'refresh_token' => $googleUser->refreshToken,
            'scopes' => $scopes,
        ]);

        return redirect('/settings?google=connected');
    }

    public function destroy(Request $request, GoogleTokenRevoker $revoker): Response
    {
        $account = $request->user()->googleAccount;

        if ($account !== null) {
            $revoker->revoke($account);
            $account->delete();
        }

        return response()->noContent();
    }
}
