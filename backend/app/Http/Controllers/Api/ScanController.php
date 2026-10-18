<?php

namespace App\Http\Controllers\Api;

use App\Enums\ScanTrigger;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScanResource;
use App\Jobs\RunScanJob;
use App\Models\Scan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class ScanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ScanResource::collection($request->user()->scans()->latest()->latest('id')->limit(10)->get());
    }

    public function show(Scan $scan): ScanResource
    {
        return new ScanResource($scan);
    }

    /**
     * Lance un scan manuel (exécuté en arrière-plan par le worker).
     *
     * Garde-fous : e-mail vérifié (on cherche l'identité confirmée du titulaire), un seul scan actif,
     * et 4 scans manuels par 30 jours (le scan sollicite des services gratuits et des sites tiers).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasVerifiedEmail(), 403, 'Vérifiez votre adresse e-mail avant de lancer une recherche.');

        // Verrou court : deux clics simultanés ne créent pas deux scans.
        $lock = Cache::lock('scan-start:'.$user->id, 10);
        abort_unless($lock->get(), 409, 'Une recherche est déjà en cours de lancement.');

        try {
            abort_if($user->scans()->active()->exists(), 409, 'Une recherche est déjà en cours.');

            $limit = (int) config('scanner.scan.monthly_limit');
            $recent = $user->scans()
                ->where('trigger', ScanTrigger::Manual)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
            abort_if($recent >= $limit, 429, "Limite de {$limit} recherches par mois atteinte.");

            $scan = $user->scans()->create(['trigger' => ScanTrigger::Manual]);
        } finally {
            $lock->release();
        }

        RunScanJob::dispatch($scan);

        return (new ScanResource($scan->refresh()))->response()->setStatusCode(202);
    }
}
