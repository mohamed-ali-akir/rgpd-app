<?php

namespace App\Http\Requests;

use App\Enums\Channel;
use App\Enums\DataType;
use App\Enums\LegalGround;
use App\Enums\RequestKind;
use App\Models\ErasureRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * Modification d'un brouillon. "sometimes" : on ne valide un champ que s'il est envoyé.
 * Les règles dépendent du droit exercé par la demande (le type ne se modifie pas après création).
 */
class UpdateErasureRequestRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $kind = $this->erasureRequest()->kind;

        return [
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:20000'],
            'data_types' => $kind->requiresDataTypes() ? ['sometimes', 'required', 'array', 'min:1'] : ['sometimes', 'array'],
            'data_types.*' => ['distinct', Rule::enum(DataType::class)],
            // Les motifs de l'art. 17 n'ont de sens que pour un effacement.
            'legal_grounds' => $kind === RequestKind::Erasure ? ['sometimes', 'required', 'array', 'min:1'] : ['prohibited'],
            'legal_grounds.*' => ['distinct', Rule::enum(LegalGround::class)],
            'details' => $kind->requiresDetails() ? ['sometimes', 'required', 'string', 'max:2000'] : ['prohibited'],
            'channel' => ['sometimes', 'required', Rule::enum(Channel::class)],
            // true : réécrit la lettre à partir des données et fondements (écrase le texte modifié).
            'regenerate_letter' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('channel') === Channel::Gmail->value && $this->erasureRequest()->organization->privacy_email === null) {
                    $validator->errors()->add('channel', 'Cet organisme n\'a pas d\'adresse e-mail de contact : envoyez la demande vous-même.');
                }
            },
        ];
    }

    public function erasureRequest(): ErasureRequest
    {
        $request = $this->route('erasureRequest');

        return $request instanceof ErasureRequest ? $request : throw new LogicException('Route sans demande.');
    }
}
