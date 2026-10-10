<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResponseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Seules les réponses possibles dans le statut actuel sont acceptées.
            'outcome' => ['required', Rule::in($this->route('erasureRequest')->responseOutcomes())],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'outcome.in' => 'Cette réponse n\'est pas possible dans le statut actuel de la demande.',
        ];
    }
}
