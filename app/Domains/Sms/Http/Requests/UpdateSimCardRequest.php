<?php

namespace App\Domains\Sms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSimCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Solde reconstate apres un rechargement manuel de la carte.
            'balance' => ['sometimes', 'integer', 'min:0'],
            'low_balance_threshold' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
