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
            // Adresse e-mail, numero de telephone, ou code compagnie pour un
            // gestionnaire : la connexion accepte les trois formes.
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            // Fourni uniquement par les comptes ayant confirme une inscription
            // 2FA ; absent, il n'est meme pas regarde pour les autres.
            'code' => ['nullable', 'string', 'max:20'],
            // Nomme l'appareil porteur du jeton : « guichet-gare-adjame »,
            // « tablette-controle-02 ». Sur une flotte de tablettes, c'est ce
            // qui permet de revoquer un appareil perdu sans deconnecter les
            // autres.
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'login' => 'identifiant',
        ];
    }
}
