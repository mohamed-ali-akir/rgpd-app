<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Resources\ErasureRequestResource;
use App\Http\Resources\FindingResource;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\ScanResource;
use App\Models\Organization;
use App\Services\Mail\GoogleTokenRevoker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Le RGPD appliqué à l'app elle-même : droit d'accès / portabilité (art. 15 et 20)
 * et droit à l'effacement (art. 17) pour nos propres utilisateurs.
 */
class AccountController extends Controller
{
    /**
     * GET /api/me/export : toutes les données de l'utilisateur, en JSON téléchargeable.
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile', 'googleAccount');
        $requests = $user->erasureRequests()->with('organization', 'events')->get();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'profile' => $user->profile ? new ProfileResource($user->profile) : null,
            // Le jeton OAuth n'est pas exporté : c'est un secret, pas une donnée sur l'utilisateur.
            'gmail' => $user->googleAccount ? [
                'email' => $user->googleAccount->email,
                'scopes' => $user->googleAccount->scopes,
                'connected_at' => $user->googleAccount->created_at->toIso8601String(),
            ] : null,
            'erasure_requests' => ErasureRequestResource::collection($requests),
            'organizations_created' => OrganizationResource::collection(Organization::where('created_by', $user->id)->get()),
            // V2 : recherches et résultats du scanner (déchiffrés pour l'export).
            'scans' => ScanResource::collection($user->scans()->latest()->get()),
            'findings' => FindingResource::collection($user->findings()->with('organization')->get()),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="mes-donnees.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * DELETE /api/me : suppression définitive du compte.
     * Les clés étrangères en cascade suppriment profil, demandes, historique et compte Google.
     */
    public function destroy(DeleteAccountRequest $request, GoogleTokenRevoker $revoker): Response
    {
        $user = $request->user();

        // D'abord révoquer chez Google, tant qu'on a encore le jeton.
        if ($user->googleAccount !== null) {
            $revoker->revoke($user->googleAccount);
        }

        Auth::guard('web')->logout();

        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            // Cette table n'a pas de clé étrangère (elle est indexée par e-mail) : pas de cascade automatique.
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
