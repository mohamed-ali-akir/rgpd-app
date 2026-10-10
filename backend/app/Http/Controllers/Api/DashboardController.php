<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ErasureRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard → { counts: { draft: 2, sent: 1, ... }, overdue: [...] }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Une seule requête SQL : SELECT status, COUNT(*) ... GROUP BY status
        $counts = $user->erasureRequests()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Tous les statuts apparaissent, même à 0, pour simplifier l'affichage côté front.
        $allCounts = [];
        foreach (RequestStatus::cases() as $status) {
            $allCounts[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $overdue = $user->erasureRequests()
            ->with('organization')
            ->where('status', RequestStatus::Overdue)
            ->orderBy('due_at')
            ->get();

        return response()->json([
            'counts' => $allCounts,
            'overdue' => ErasureRequestResource::collection($overdue),
        ]);
    }
}
