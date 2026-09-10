<?php

namespace App\Models;

use Database\Factories\StationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Gare routiere ou point d'embarquement.
 *
 * @property string $id
 * @property string $city_id
 * @property string|null $company_id
 * @property string $name
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read City $city
 * @property-read Company|null $company
 */
class Station extends Model
{
    /** @use HasFactory<StationFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'city_id', 'company_id', 'name', 'address',
        'latitude', 'longitude', 'phone', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            // Chaines et non flottants : une latitude arrondie par la
            // representation binaire deplace un point d'embarquement de
            // plusieurs metres, et c'est ce point que le voyageur cherche.
            'latitude' => 'string',
            'longitude' => 'string',
        ];
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Gare partagee entre compagnies, par opposition a un terminal prive. */
    public function isShared(): bool
    {
        return $this->company_id === null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['city_id', 'company_id', 'name', 'address', 'phone', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
