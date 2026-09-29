<?php

namespace App\Domains\Sms\Http\Resources;

use App\Models\NotificationDispatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationDispatch */
class SmsDispatchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipient' => $this->recipient,
            'reference' => $this->template,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'attempts' => $this->attempts,
            'error' => $this->error,
            'operator' => $this->whenLoaded('simCard', fn () => $this->simCard?->operator->label()),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
