<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode démo : bloque ce qui ferait sortir de la démo (créer de vrais comptes, connecter un vrai Gmail,
 * supprimer le compte de démo partagé par tous les visiteurs).
 */
class BlockInDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if((bool) config('app.demo'), 403, 'Indisponible dans la version de démonstration.');

        return $next($request);
    }
}
