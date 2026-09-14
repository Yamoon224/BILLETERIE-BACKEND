<?php

namespace App\Domains\Favorites\Http\Resources;

use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Favorite */
class FavoriteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'origin_city' => $this->whenLoaded('originCity', fn () => [
                'id' => $this->originCity->id,
                'name' => $this->originCity->name,
                'slug' => $this->originCity->slug,
            ]),
            'destination_city' => $this->whenLoaded('destinationCity', fn () => [
                'id' => $this->destinationCity->id,
                'name' => $this->destinationCity->name,
                'slug' => $this->destinationCity->slug,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
