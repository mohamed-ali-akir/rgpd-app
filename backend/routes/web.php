<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\GoogleAccountController;
use Illuminate\Support\Facades\Route;

/*
 * Pas de pages web ici : en production, Nginx sert le build React à la racine
 * et ne transmet à Laravel que /api et /sanctum (voir deploy/nginx.conf).
 *
 * Pourquoi ces routes /api/... sont-elles dans web.php et pas dans api.php ?
 * Sanctum n'active la session sur les routes d'API que si la requête vient du front
 * (en-tête Referer ou Origin = un domaine "stateful"). Or au retour de Google
 * (callback OAuth), la requête vient de accounts.google.com : sans session,
 * l'utilisateur ne serait pas reconnu. Le groupe "web" démarre TOUJOURS la session.
 * On y range donc toutes les routes atteintes par navigation (liens, redirections).
 */
Route::prefix('api')->middleware('auth')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAccountController::class, 'redirect'])->middleware('not-in-demo');
    Route::get('/auth/google/callback', [GoogleAccountController::class, 'callback'])->middleware('not-in-demo');

    Route::get('/requests/{erasureRequest}/complaint.pdf', ComplaintController::class)->can('view', 'erasureRequest');
    Route::get('/me/export', [AccountController::class, 'export']);
});
