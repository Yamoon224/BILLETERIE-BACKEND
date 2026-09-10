<?php

namespace App\Domains\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            // Nomme l'appareil porteur du jeton : « guichet-gare-adjame »,
            // « tablette-controle-02 ». Sur une flotte de tablettes, c'est ce
            // qui permet de revoquer un appareil perdu sans deconnecter les
            // autres.
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
