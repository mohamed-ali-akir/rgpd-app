<?php

namespace App\Http\Requests;

use App\Enums\Channel;
use App\Enums\DataType;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreErasureRequestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:https,http', 'max:2048'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'data_types' => ['required', 'array', 'min:1'],
            'data_types.*' => ['distinct', Rule::enum(DataType::class)],
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
        ];
    }
}
