<?php

namespace App\Domains\Promotions\Http\Requests;

use App\Domains\Promotions\Enums\PromotionKind;
use App\Domains\Promotions\Enums\PromotionZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'zone' => ['required', Rule::in(PromotionZone::values())],
            'kind' => ['nullable', Rule::in(PromotionKind::values())],
            'advertiser_name' => ['nullable', 'string', 'max:255'],
            'partner_id' => ['nullable', 'uuid', 'exists:partners,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
