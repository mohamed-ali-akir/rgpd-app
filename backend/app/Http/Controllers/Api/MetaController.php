<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/meta (public) : informations dont le front a besoin avant même la connexion.
 */
class MetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $demo = (bool) config('app.demo');

        return response()->json([
            'demo' => $demo,
            // Identifiants du compte de démo, affichés sur la page de connexion (uniquement en démo).
            'demo_credentials' => $demo ? [
                'email' => config('app.demo_email'),
                'password' => config('app.demo_password'),
            ] : null,
        ]);
    }
}
