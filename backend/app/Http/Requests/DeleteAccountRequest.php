<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Action irréversible : on redemande le mot de passe.
            'password' => ['required', 'current_password'],
        ];
    }
}
