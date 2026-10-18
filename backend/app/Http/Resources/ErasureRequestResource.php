<?php

namespace App\Http\Resources;

use App\Enums\RequestStatus;
use App\Models\ErasureRequest;
use App\Services\LegalAdvisor;
use App\Services\LetterGenerator;
use App\Services\Letters\LetterData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ErasureRequest */
class ErasureRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'details' => $this->details,
            'url' => $this->url,
            'status' => $this->status,
            'channel' => $this->channel,
            'data_types' => $this->data_types,
            'legal_grounds' => $this->legal_grounds,
            'subject' => $this->subject,
            'body' => $this->body,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'verification' => $this->verification,
            'next_check_at' => $this->next_check_at?->toIso8601String(),
            'checks' => ErasureCheckResource::collection($this->whenLoaded('checks')),
            'created_at' => $this->created_at->toIso8601String(),
            'organization' => new OrganizationResource($this->whenLoaded('organization')),

            // Les champs suivants ne sont calculés que pour la page de détail
            // (quand l'historique est chargé), pour éviter des requêtes en trop dans les listes.
            'events' => RequestEventResource::collection($this->whenLoaded('events')),
            'warnings' => $this->whenLoaded('events', fn () => app(LegalAdvisor::class)->warnings($this->organization, $this->kind)),
            'available_actions' => $this->whenLoaded('events', fn () => $this->availableActions()),
            'response_outcomes' => $this->whenLoaded('events', fn () => $this->responseOutcomes()),
            // Texte de relance, affiché quand le délai est dépassé (à copier si canal manuel).
            'reminder' => $this->whenLoaded('events', fn () => $this->status === RequestStatus::Overdue
                ? $this->reminderLetter()
                : null),
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function reminderLetter(): array
    {
        $letter = app(LetterGenerator::class)->reminder(LetterData::fromRequest($this->resource), $this->sent_at, $this->due_at);

        return ['subject' => $letter->subject, 'body' => $letter->body];
    }
}
