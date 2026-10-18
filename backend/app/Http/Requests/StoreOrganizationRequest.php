<?php

namespace App\Http\Requests;

use App\Enums\OrganizationCategory;
use App\Services\OrganizationResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreOrganizationRequest extends FormRequest
{
    /**
     * Normalise le domaine AVANT la validation : "https://www.Site.fr/page" → "site.fr".
     * Ainsi la règle "unique" compare des domaines au même format.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            try {
                $this->merge(['domain' => app(OrganizationResolver::class)->extractDomain($this->input('domain'))]);
            } catch (InvalidArgumentException) {
                // laissé tel quel : la règle "regex" ci-dessous produira l'erreur
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/', 'unique:organizations,domain'],
            'category' => ['required', Rule::enum(OrganizationCategory::class)],
            'privacy_email' => ['nullable', 'email', 'max:255'],
            'privacy_form_url' => ['nullable', 'url:https,http', 'max:2048'],
            'dpo_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'domain.unique' => 'Cet organisme existe déjà.',
            'domain.regex' => 'Domaine invalide (exemple : exemple.fr).',
        ];
    }
}
