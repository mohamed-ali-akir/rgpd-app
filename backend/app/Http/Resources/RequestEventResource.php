<?php

namespace App\Http\Resources;

use App\Models\RequestEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RequestEvent */
class RequestEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->type->label(),
            'payload' => $this->payload ?? (object) [],
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
