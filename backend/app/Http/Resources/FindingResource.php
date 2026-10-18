<?php

namespace App\Http\Resources;

use App\Models\Finding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Finding */
class FindingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'url' => $this->url,
            'domain' => $this->domain,
            'title' => $this->title,
            'snippet' => $this->snippet,
            'meta' => $this->meta ?? (object) [],
            'matched' => $this->matched,
            'confidence' => $this->confidence,
            'status' => $this->status,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'erasure_request_id' => $this->erasure_request_id,
            'first_seen_at' => $this->first_seen_at->toIso8601String(),
            'last_seen_at' => $this->last_seen_at->toIso8601String(),
        ];
    }
}
