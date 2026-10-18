<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkSentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Date d'envoi réelle (ex. courrier posté hier). Par défaut : maintenant.
            'sent_at' => ['nullable', 'date', 'before_or_equal:now', 'after:-1 year'],
        ];
    }
}
