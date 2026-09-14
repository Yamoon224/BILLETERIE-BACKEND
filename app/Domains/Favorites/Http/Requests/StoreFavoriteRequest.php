<?php

namespace App\Domains\Favorites\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'origin_city_id' => ['required', 'uuid', 'exists:cities,id', 'different:destination_city_id'],
            'destination_city_id' => ['required', 'uuid', 'exists:cities,id'],
        ];
    }
}
