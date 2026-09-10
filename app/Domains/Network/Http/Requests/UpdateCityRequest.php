<?php

namespace App\Domains\Network\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $cityId = $this->route('city')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => [
                'sometimes', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('cities', 'slug')->ignore($cityId),
            ],
            'region' => ['nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
