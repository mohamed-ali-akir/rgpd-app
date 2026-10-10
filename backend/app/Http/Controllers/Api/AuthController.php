<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Authentification Sanctum en mode SPA : ce sont des sessions Laravel classiques
 * (cookie de session + cookie XSRF-TOKEN), pas des jetons d'API.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Transaction : soit l'utilisateur ET son profil sont créés, soit aucun des deux.
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['first_name'].' '.$data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'], // haché automatiquement par le cast "hashed"
            ]);
            $user->profile()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ]);

            return $user;
        });

        // L'événement Registered déclenche l'envoi du lien de vérification (MustVerifyEmail).
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        // Nouvel identifiant de session après connexion : protège contre la fixation de session.
        $request->session()->regenerate();

        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
