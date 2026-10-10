<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'category' => $this->category,
            'category_label' => $this->category->label(),
            'privacy_email' => $this->privacy_email,
            'privacy_form_url' => $this->privacy_form_url,
            'dpo_name' => $this->dpo_name,
            'verified' => $this->isVerified(),
            // Ajouté par un utilisateur (et non par le seeder) : contacts à prendre avec prudence.
            'user_created' => $this->created_by !== null,
        ];
    }
}
