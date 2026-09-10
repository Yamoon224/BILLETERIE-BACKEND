<?php

namespace App\Models;

use App\Domains\Network\Enums\VehicleClass;
use App\Domains\Network\Support\SeatMap;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Vehicule d'une compagnie.
 *
 * @property string $id
 * @property string $company_id
 * @property string $registration
 * @property string|null $model
 * @property VehicleClass $class
 * @property int $seat_capacity
 * @property int $seats_per_row
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'registration', 'model', 'class',
        'seat_capacity', 'seats_per_row', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'class' => VehicleClass::class,
            'seat_capacity' => 'integer',
            'seats_per_row' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Plan de salle deduit de la capacite et du nombre de places par rangee. */
    public function seatMap(): SeatMap
    {
        return new SeatMap($this->seat_capacity, $this->seats_per_row);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Trip, $this> */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['company_id', 'registration', 'model', 'class', 'seat_capacity', 'seats_per_row', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
