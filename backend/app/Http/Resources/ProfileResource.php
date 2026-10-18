<?php

namespace App\Http\Resources;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Profile */
class ProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Les valeurs sont déchiffrées automatiquement par le cast "encrypted".
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'postal_address' => $this->postal_address,
            'phone' => $this->phone,
            'auto_scan' => (bool) $this->auto_scan,
        ];
    }
}
