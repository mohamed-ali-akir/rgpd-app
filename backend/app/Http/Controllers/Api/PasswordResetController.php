<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mot de passe oublié, en deux temps :
 *  1. POST /api/forgot-password : Laravel crée un jeton (table password_reset_tokens, haché)
 *     et envoie le lien par e-mail ;
 *  2. POST /api/reset-password : la page React renvoie le jeton avec le nouveau mot de passe.
 */
class PasswordResetController extends Controller
{
    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        // Même réponse que l'adresse existe ou non : on ne révèle pas qui a un compte
        // (sinon le formulaire permettrait de tester des adresses une par une).
        return response()->json([
            'message' => 'Si un compte existe pour cette adresse, un lien de réinitialisation vient de lui être envoyé.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // haché par le cast "hashed"
                    'remember_token' => Str::random(60),
                ])->save();

                // Déconnecte toutes les sessions ouvertes : si quelqu'un d'autre était connecté
                // avec l'ancien mot de passe, il perd l'accès.
                DB::table('sessions')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Jeton invalide ou expiré, ou adresse inconnue : message traduit (lang/fr/passwords.php).
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }
}
