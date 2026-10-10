<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\OrganizationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class OrganizationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        $organizations = Organization::query()
            ->search($request->query('search'))
            ->orderBy('name')
            ->limit(50)
            ->get();

        return OrganizationResource::collection($organizations);
    }

    /**
     * GET /api/organizations/resolve?url=... → { domain, organization | null }
     */
    public function resolve(Request $request, OrganizationResolver $resolver): JsonResponse
    {
        $request->validate(['url' => ['required', 'string', 'max:2048']]);

        try {
            $result = $resolver->resolve($request->query('url'));
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'URL invalide.', 'errors' => ['url' => ['URL invalide.']]], 422);
        }

        return response()->json([
            'domain' => $result['domain'],
            'organization' => $result['organization'] ? new OrganizationResource($result['organization']) : null,
        ]);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = new Organization($request->validated());
        // created_by n'est pas "fillable" : on le renseigne explicitement, jamais depuis la requête.
        $organization->created_by = $request->user()->id;
        $organization->save();

        return (new OrganizationResource($organization))->response()->setStatusCode(201);
    }
}
