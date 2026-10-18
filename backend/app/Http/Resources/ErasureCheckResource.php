<?php

namespace App\Http\Resources;

use App\Models\ErasureCheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ErasureCheck */
class ErasureCheckResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'outcome' => $this->outcome,
            'failure' => $this->failure,
            'http_status' => $this->http_status,
            'matched' => $this->matched ?? [],
            'manual' => $this->manual,
            'checked_at' => $this->checked_at->toIso8601String(),
        ];
    }
}
