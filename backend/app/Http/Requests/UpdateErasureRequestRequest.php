<?php

namespace App\Http\Requests;

use App\Enums\Channel;
use App\Enums\DataType;
use App\Enums\LegalGround;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Modification d'un brouillon. "sometimes" : on ne valide un champ que s'il est envoyé.
 */
class UpdateErasureRequestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:20000'],
            'data_types' => ['sometimes', 'required', 'array', 'min:1'],
            'data_types.*' => ['distinct', Rule::enum(DataType::class)],
            'legal_grounds' => ['sometimes', 'required', 'array', 'min:1'],
            'legal_grounds.*' => ['distinct', Rule::enum(LegalGround::class)],
            'channel' => ['sometimes', 'required', Rule::enum(Channel::class)],
            // true : réécrit la lettre à partir des données et fondements (écrase le texte modifié).
            'regenerate_letter' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $erasureRequest = $this->route('erasureRequest');
                if ($this->input('channel') === Channel::Gmail->value && $erasureRequest->organization->privacy_email === null) {
                    $validator->errors()->add('channel', 'Cet organisme n\'a pas d\'adresse e-mail de contact : envoyez la demande vous-même.');
                }
            },
        ];
    }
}
