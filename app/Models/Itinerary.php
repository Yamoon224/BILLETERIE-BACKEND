<?php

namespace App\Models;

use Database\Factories\ItineraryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Itineraire : une liaison ville a ville exploitee par une compagnie.
 *
 * @property string $id
 * @property string $company_id
 * @property string $origin_city_id
 * @property string $destination_city_id
 * @property int|null $distance_km
 * @property int $duration_minutes
 * @property int $base_price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read City $originCity
 * @property-read City $destinationCity
 */
class Itinerary extends Model
{
    /** @use HasFactory<ItineraryFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'origin_city_id', 'destination_city_id',
        'distance_km', 'duration_minutes', 'base_price', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'distance_km' => 'integer',
            'duration_minutes' => 'integer',
            // Entier : le franc CFA n'a pas de sous-unite. Voir Money.
            'base_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<City, $this> */
    public function originCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'origin_city_id');
    }

    /** @return BelongsTo<City, $this> */
    public function destinationCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'destination_city_id');
    }

    /** @return HasMany<Trip, $this> */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'company_id', 'origin_city_id', 'destination_city_id',
                'distance_km', 'duration_minutes', 'base_price', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
