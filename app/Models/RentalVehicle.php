<?php

namespace App\Models;

use App\Domains\CarRental\Enums\FuelType;
use App\Domains\CarRental\Enums\RentalVehicleCategory;
use App\Domains\CarRental\Enums\TransmissionType;
use Database\Factories\RentalVehicleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Vehicule propose a la location courte duree (aeroport, ville).
 *
 * @property string $id
 * @property string $partner_id
 * @property string $city_id
 * @property string $brand
 * @property string $model
 * @property int|null $year
 * @property RentalVehicleCategory $category
 * @property TransmissionType $transmission
 * @property FuelType $fuel_type
 * @property int $seats
 * @property int $price_per_day
 * @property bool $with_driver_available
 * @property string|null $plate_number
 * @property string|null $cover_photo_url
 * @property list<string>|null $photo_urls
 * @property bool $is_featured
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Partner $partner
 * @property-read City $city
 */
class RentalVehicle extends Model
{
    /** @use HasFactory<RentalVehicleFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'partner_id', 'city_id', 'brand', 'model', 'year', 'category', 'transmission',
        'fuel_type', 'seats', 'price_per_day', 'with_driver_available', 'plate_number',
        'cover_photo_url', 'photo_urls', 'is_featured', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'category' => RentalVehicleCategory::class,
            'transmission' => TransmissionType::class,
            'fuel_type' => FuelType::class,
            'seats' => 'integer',
            'price_per_day' => 'integer',
            'with_driver_available' => 'boolean',
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
                'partner_id', 'city_id', 'brand', 'model', 'category', 'transmission',
                'fuel_type', 'seats', 'price_per_day', 'is_featured', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
