<?php

namespace App\Http\Controllers\Api;

use App\Enums\FindingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FindingResource;
use App\Models\Finding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class FindingController extends Controller
{
    /**
     * Résultats à examiner d'abord (nouveaux), puis par confiance décroissante.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::enum(FindingStatus::class)]]);

        $findings = $request->user()->findings()
            ->with('organization')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("case when status = 'new' then 0 when status = 'confirmed' then 1 else 2 end")
            ->orderByDesc('confidence')
            ->orderByDesc('last_seen_at')
            ->limit(500)
            ->get();

        return FindingResource::collection($findings);
    }

    /**
     * Décision de l'utilisateur : "c'est moi", "pas moi", "ignorer" (ou retour à "à examiner").
     * Un résultat déjà transformé en demande garde sa confirmation.
     */
    public function update(Request $request, Finding $finding): FindingResource
    {
        $data = $request->validate(['status' => ['required', Rule::enum(FindingStatus::class)]]);
        $status = FindingStatus::from($data['status']);

        abort_if(
            $finding->erasure_request_id !== null && $status !== FindingStatus::Confirmed,
            409,
            'Une demande a déjà été créée à partir de ce résultat.',
        );

        $finding->decide($status);

        return new FindingResource($finding->load('organization'));
    }
}
