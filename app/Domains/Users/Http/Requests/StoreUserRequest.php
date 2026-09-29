<?php

namespace App\Domains\Users\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')],
            'password' => ['required', Password::min(8)],
            // Connexion rapide au guichet ; sans objet pour les autres roles,
            // ou l'API ne verifie de toute facon jamais ce code.
            'pin_code' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            // Impose par le controleur pour un gestionnaire de compagnie : il
            // ne cree des comptes que dans la sienne. Meme logique pour
            // `partner_id` cote gestionnaire de partenaire.
            'company_id' => ['nullable', 'uuid', 'exists:companies,id'],
            'partner_id' => ['nullable', 'uuid', 'exists:partners,id'],
            // Gare d'affectation d'un agent de guichet ; sans objet pour les
            // autres roles.
            'station_id' => ['nullable', 'uuid', 'exists:stations,id'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
