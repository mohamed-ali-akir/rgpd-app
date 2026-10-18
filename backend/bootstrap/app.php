<?php

use App\Exceptions\InvalidTransitionException;
use App\Http\Middleware\BlockInDemo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Mode SPA de Sanctum : les requêtes venant du front (domaines "stateful")
        // passent par la session + la vérification CSRF, comme une app web classique.
        $middleware->statefulApi();

        // Visiteur non connecté sur une route "auth" de web.php : on le renvoie vers la page React.
        $middleware->redirectGuestsTo('/login');

        // Routes désactivées en mode démo (inscription, Gmail, suppression du compte de démo…).
        $middleware->alias(['not-in-demo' => BlockInDemo::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Une transition de statut interdite est une erreur de l'utilisateur (409), pas un bug serveur (500).
        $exceptions->render(fn (InvalidTransitionException $e) => response()->json([
            'message' => $e->getMessage(),
        ], 409));

        // Message du limiteur de requêtes (throttle) en français.
        $exceptions->render(fn (ThrottleRequestsException $e) => response()->json([
            'message' => 'Trop de tentatives. Réessayez dans quelques minutes.',
        ], 429, $e->getHeaders()));
    })->create();
