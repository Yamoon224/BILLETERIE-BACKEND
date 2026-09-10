<?php

namespace App\Domains\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // L'ancien mot de passe est exige : un poste de guichet reste
            // souvent ouvert entre deux clients, et sans cette verification
            // quiconque passe devant un ecran deverrouille pourrait
            // s'approprier le compte d'un agent — et encaisser sous son nom.
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.different' => 'Le nouveau mot de passe doit differer de l ancien.',
        ];
    }
}
