<?php

namespace App\Http\Controllers\Api;

use App\Enums\DataType;
use App\Enums\EventType;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreErasureRequestRequest;
use App\Http\Requests\UpdateErasureRequestRequest;
use App\Http\Resources\ErasureRequestResource;
use App\Models\ErasureRequest;
use App\Models\Organization;
use App\Services\LegalAdvisor;
use App\Services\LetterGenerator;
use App\Services\Letters\LetterData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
     * Crée un brouillon : fondements suggérés par LegalAdvisor + lettre générée.
     */
    public function store(StoreErasureRequestRequest $request, LegalAdvisor $advisor, LetterGenerator $letters): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $organization = Organization::findOrFail($data['organization_id']);
        $dataTypes = array_map(fn (string $t) => DataType::from($t), $data['data_types']);

        $advice = $advisor->advise(
            $organization,
            selfPublished: $request->boolean('self_published'),
            hasAccount: $request->boolean('has_account'),
        );
        $letter = $letters->erasure(LetterData::make($user, $organization, $data['url'], $dataTypes, $advice->legalGrounds));

        $erasureRequest = DB::transaction(function () use ($user, $organization, $data, $dataTypes, $advice, $letter, $request) {
            $erasureRequest = $user->erasureRequests()->create([
                'organization_id' => $organization->id,
                'url' => $data['url'],
                'data_types' => $dataTypes,
                'legal_grounds' => $advice->legalGrounds,
                'channel' => $data['channel'] ?? $advice->suggestedChannel,
                'subject' => $letter->subject,
                'body' => $letter->body,
            ]);

            // On garde la trace des réponses aux questions et de la case "mes propres données".
            $erasureRequest->recordEvent(EventType::Created, [
                'own_data_confirmed' => true,
                'self_published' => $request->boolean('self_published'),
                'has_account' => $request->boolean('has_account'),
            ]);

            return $erasureRequest;
        });

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
            $letter = $letters->erasure(LetterData::fromRequest($erasureRequest));
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
