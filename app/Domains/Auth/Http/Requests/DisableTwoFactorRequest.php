<?php

namespace App\Domains\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Desactiver la 2FA affaiblit le compte : on redemande le mot de
            // passe, comme pour tout changement qui touche a la securite du
            // compte plutot qu'a son contenu.
            'password' => ['required', 'string'],
        ];
    }
}
