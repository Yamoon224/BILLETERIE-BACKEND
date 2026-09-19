<?php

namespace App\Models;

use Database\Factories\RouteGridEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Ligne de la grille des trajets : ce que le voyageur voit pour une liaison
 * pas encore reservable en ligne (prix indicatif, duree, horaires habituels).
 *
 * @property string $id
 * @property string $origin_city_id
 * @property string $destination_city_id
 * @property string|null $company_name
 * @property int $price
 * @property int|null $distance_km
 * @property int|null $duration_minutes
 * @property list<string>|null $departure_times
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read City $originCity
 * @property-read City $destinationCity
 */
class RouteGridEntry extends Model
{
    /** @use HasFactory<RouteGridEntryFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'origin_city_id', 'destination_city_id', 'company_name',
        'price', 'distance_km', 'duration_minutes',
        'departure_times', 'notes', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'distance_km' => 'integer',
            'duration_minutes' => 'integer',
            'departure_times' => 'array',
            'is_active' => 'boolean',
        ];
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'origin_city_id', 'destination_city_id', 'company_name',
                'price', 'distance_km', 'duration_minutes',
                'departure_times', 'notes', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
