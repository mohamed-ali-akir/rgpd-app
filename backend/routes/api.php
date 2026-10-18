<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ErasureRequestActionController;
use App\Http\Controllers\Api\ErasureRequestController;
use App\Http\Controllers\Api\FindingController;
use App\Http\Controllers\Api\FindingRequestController;
use App\Http\Controllers\Api\GoogleAccountController;
use App\Http\Controllers\Api\GuidedCheckController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ScanController;
use Illuminate\Support\Facades\Route;

// Toutes ces routes sont préfixées par /api (voir bootstrap/app.php).

// --- Invités ---------------------------------------------------------------
// Limiteur "guest-forms" (AppServiceProvider) : 6 essais par minute et par formulaire, contre la force brute.
// Public : mode démo et identifiants de démonstration.
Route::get('/meta', MetaController::class);

Route::middleware('throttle:guest-forms')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('not-in-demo');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('not-in-demo');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('not-in-demo');
});

Route::get('/email/verify/{user}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// --- Utilisateurs connectés -------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1');

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/organizations/resolve', [OrganizationController::class, 'resolve']);
    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::post('/organizations', [OrganizationController::class, 'store']);

    Route::get('/dashboard', DashboardController::class);

    Route::get('/requests', [ErasureRequestController::class, 'index']);
    Route::post('/requests', [ErasureRequestController::class, 'store']);
    // "can:view,erasureRequest" appelle ErasureRequestPolicy::view() AVANT le contrôleur
    // (et avant la validation) : la demande d'un autre utilisateur renvoie toujours 403.
    Route::get('/requests/{erasureRequest}', [ErasureRequestController::class, 'show'])->can('view', 'erasureRequest');
    Route::patch('/requests/{erasureRequest}', [ErasureRequestController::class, 'update'])->can('update', 'erasureRequest');
    Route::delete('/requests/{erasureRequest}', [ErasureRequestController::class, 'destroy'])->can('delete', 'erasureRequest');

    // Actions qui font avancer la demande dans la machine à états.
    Route::controller(ErasureRequestActionController::class)
        ->prefix('/requests/{erasureRequest}')
        ->middleware('can:update,erasureRequest')
        ->group(function () {
            // E-mail vérifié, Gmail connecté et limite d'envois : vérifiés dans le contrôleur
            // (la relance en canal manuel n'a pas besoin de Gmail).
            Route::post('/send', 'send');
            Route::post('/reminder', 'reminder');
            Route::post('/mark-sent', 'markSent');
            Route::post('/responses', 'response');
            Route::post('/complaint', 'complaint');
            Route::post('/close', 'close');
            Route::post('/verify', 'verify');
        });

    // Scanner (V2) : recherche des données publiques du titulaire du compte.
    Route::get('/scans', [ScanController::class, 'index']);
    Route::post('/scans', [ScanController::class, 'store']);
    Route::get('/scans/{scan}', [ScanController::class, 'show'])->can('view', 'scan');
    Route::get('/findings', [FindingController::class, 'index']);
    Route::patch('/findings/{finding}', [FindingController::class, 'update'])->can('update', 'finding');
    Route::post('/findings/requests', [FindingRequestController::class, 'store']);
    Route::get('/guided-checks', GuidedCheckController::class);

    Route::delete('/google-account', [GoogleAccountController::class, 'destroy'])->middleware('not-in-demo');
    Route::delete('/me', [AccountController::class, 'destroy'])->middleware('not-in-demo');
});

// Les routes ouvertes par une NAVIGATION du navigateur (redirection OAuth, téléchargements)
// sont dans routes/web.php : voir l'explication là-bas.
