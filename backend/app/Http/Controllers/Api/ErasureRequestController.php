<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateDraftRequest;
use App\Actions\DraftInput;
use App\Enums\Channel;
use App\Enums\DataType;
use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreErasureRequestRequest;
use App\Http\Requests\UpdateErasureRequestRequest;
use App\Http\Resources\ErasureRequestResource;
use App\Models\ErasureRequest;
use App\Models\Organization;
use App\Services\LetterGenerator;
use App\Services\Letters\LetterData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ErasureRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::enum(RequestStatus::class)]]);

        // On part de $request->user()->erasureRequests() : impossible de lister celles des autres.
        $requests = $request->user()->erasureRequests()
            ->with('organization')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->get();

        return ErasureRequestResource::collection($requests);
    }

    /**
     * Crée un brouillon : fondements suggérés, lettre générée (voir CreateDraftRequest).
     */
    public function store(StoreErasureRequestRequest $request, CreateDraftRequest $createDraft): JsonResponse
    {
        $data = $request->validated();
        $kind = RequestKind::from($data['kind'] ?? RequestKind::Erasure->value);

        $erasureRequest = $createDraft->handle(
            $request->user(),
            Organization::findOrFail($data['organization_id']),
            new DraftInput(
                url: $data['url'],
                kind: $kind,
                dataTypes: array_map(fn (string $t) => DataType::from($t), $data['data_types'] ?? []),
                details: $kind->requiresDetails() ? $data['details'] : null,
                selfPublished: $request->boolean('self_published'),
                hasAccount: $request->boolean('has_account'),
                channel: isset($data['channel']) ? Channel::from($data['channel']) : null,
                // On garde la trace de la case « je fais cette demande pour mes propres données ».
                context: ['own_data_confirmed' => true],
            ),
        );

        return (new ErasureRequestResource($erasureRequest->load('organization', 'events')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ErasureRequest $erasureRequest): ErasureRequestResource
    {
        return new ErasureRequestResource($erasureRequest->load('organization', 'events'));
    }

    /**
     * Modifier un brouillon (texte, données, fondements, canal).
     */
    public function update(UpdateErasureRequestRequest $request, ErasureRequest $erasureRequest, LetterGenerator $letters): ErasureRequestResource
    {
        abort_unless($erasureRequest->isDraft(), 409, 'Seul un brouillon peut être modifié.');

        $erasureRequest->fill($request->safe()->except('regenerate_letter'));

        if ($request->boolean('regenerate_letter')) {
            $letter = $letters->initial(LetterData::fromRequest($erasureRequest));
            $erasureRequest->subject = $letter->subject;
            $erasureRequest->body = $letter->body;
        }

        $erasureRequest->save();

        return new ErasureRequestResource($erasureRequest->load('organization', 'events'));
    }

    public function destroy(ErasureRequest $erasureRequest): Response
    {
        // L'historique part avec (clé étrangère en cascade).
        $erasureRequest->delete();

        return response()->noContent();
    }
}
