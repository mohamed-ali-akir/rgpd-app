<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateRequestsFromFindings;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FindingRequestController extends Controller
{
    /**
     * POST /api/findings/requests { finding_ids: [1, 2] }
     * → { created: [{finding_id, request_id}], skipped: [{finding_id, reason}] }
     */
    public function store(Request $request, CreateRequestsFromFindings $action): JsonResponse
    {
        $data = $request->validate([
            'finding_ids' => ['required', 'array', 'min:1', 'max:50'],
            'finding_ids.*' => ['integer', 'distinct'],
        ]);

        $result = $action->handle($request->user(), array_values(array_map('intval', $data['finding_ids'])));

        return response()->json($result, $result['created'] === [] ? 200 : 201);
    }
}
