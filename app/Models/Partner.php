<?php

namespace App\Models;

use App\Domains\Partners\Enums\PartnerType;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Proprietaire ou agence partenaire : appartements, location de vehicules.
 *
 * @property string $id
 * @property string $name
 * @property PartnerType $type
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $city_id
 * @property string|null $logo_path
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read City|null $city
 */
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'name', 'type', 'phone', 'whatsapp', 'email', 'city_id',
        'logo_path', 'description', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PartnerType::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<Apartment, $this> */
    public function apartments(): HasMany
    {
        return $this->hasMany(Apartment::class);
    }

    /** @return HasMany<RentalVehicle, $this> */
    public function rentalVehicles(): HasMany
    {
        return $this->hasMany(RentalVehicle::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'phone', 'whatsapp', 'email', 'city_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
