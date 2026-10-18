<?php

namespace App\Http\Requests;

use App\Enums\Channel;
use App\Enums\DataType;
use App\Enums\RequestKind;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreErasureRequestRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $kind = RequestKind::tryFrom((string) $this->input('kind', RequestKind::Erasure->value)) ?? RequestKind::Erasure;

        return [
            'url' => ['required', 'url:https,http', 'max:2048'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'kind' => ['sometimes', Rule::enum(RequestKind::class)],
            // Une demande d'accès porte sur toutes les données : cocher des types est alors facultatif.
            'data_types' => $kind->requiresDataTypes() ? ['required', 'array', 'min:1'] : ['sometimes', 'array'],
            'data_types.*' => ['distinct', Rule::enum(DataType::class)],
            // Correction demandée (rectification) ou motif (limitation).
            'details' => $kind->requiresDetails() ? ['required', 'string', 'max:2000'] : ['prohibited'],
            // Questions sur l'origine des données (servent à LegalAdvisor).
            'self_published' => ['boolean'],
            'has_account' => ['boolean'],
            'channel' => ['nullable', Rule::enum(Channel::class)],
            // Anti-abus : case « je fais cette demande pour mes propres données ».
            'own_data_confirmed' => ['accepted'],
        ];
    }

    /**
     * Règle qui dépend de plusieurs champs : le canal Gmail exige une adresse e-mail de contact.
     *
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('channel') !== Channel::Gmail->value || $validator->errors()->isNotEmpty()) {
                    return;
                }
                if (Organization::find($this->input('organization_id'))?->privacy_email === null) {
                    $validator->errors()->add('channel', 'Cet organisme n\'a pas d\'adresse e-mail de contact : envoyez la demande vous-même.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'own_data_confirmed.accepted' => 'Vous devez confirmer que la demande concerne vos propres données.',
            'data_types.required' => 'Sélectionnez au moins un type de donnée.',
            'details.required' => 'Précisez la correction demandée ou le motif de la limitation.',
            'details.prohibited' => 'Ce champ ne concerne que la rectification et la limitation.',
        ];
    }
}
