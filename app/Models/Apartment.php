<?php

namespace App\Models;

use Database\Factories\ApartmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Appartement meuble propose a la location courte duree.
 *
 * @property string $id
 * @property string $partner_id
 * @property string $city_id
 * @property string $title
 * @property string|null $description
 * @property string|null $neighborhood
 * @property string|null $address_line
 * @property int $bedrooms
 * @property int $bathrooms
 * @property int $capacity
 * @property int $price_per_night
 * @property list<string>|null $amenities
 * @property string|null $cover_photo_url
 * @property list<string>|null $photo_urls
 * @property bool $is_featured
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Partner $partner
 * @property-read City $city
 */
class Apartment extends Model
{
    /** @use HasFactory<ApartmentFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'partner_id', 'city_id', 'title', 'description', 'neighborhood', 'address_line',
        'bedrooms', 'bathrooms', 'capacity', 'price_per_night', 'amenities',
        'cover_photo_url', 'photo_urls', 'is_featured', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'capacity' => 'integer',
            'price_per_night' => 'integer',
            'amenities' => 'array',
            'photo_urls' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'partner_id', 'city_id', 'title', 'neighborhood', 'bedrooms', 'bathrooms',
                'capacity', 'price_per_night', 'is_featured', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
