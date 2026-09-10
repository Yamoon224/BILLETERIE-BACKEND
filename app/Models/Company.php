<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Compagnie de transport partenaire.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $logo_path
 * @property int|null $commission_per_mille
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'code', 'name', 'legal_name', 'phone', 'email', 'logo_path',
        'commission_per_mille', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_per_mille' => 'integer',
        ];
    }

    /**
     * Taux de commission reellement applicable.
     *
     * Resolu ici et nulle part ailleurs : le `null` en base signifie « suivre
     * le bareme de la plateforme », et laisser chaque appelant retomber
     * lui-meme sur la configuration ferait diverger le taux affiche au
     * gestionnaire de celui reellement facture.
     */
    public function effectiveCommissionPerMille(): int
    {
        return $this->commission_per_mille ?? (int) config('ticketing.commission_per_mille');
    }

    /** @return HasMany<Station, $this> */
    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** @return HasMany<Itinerary, $this> */
    public function itineraries(): HasMany
    {
        return $this->hasMany(Itinerary::class);
    }

    /** @return HasMany<Trip, $this> */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'legal_name', 'phone', 'email', 'commission_per_mille', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
