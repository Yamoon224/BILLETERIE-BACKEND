<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Ville desservie. Referentiel partage par toutes les compagnies.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $region
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'region', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Le slug est la cle de resolution des URL de recherche partagees : un lien
     * envoye par messagerie doit rester lisible et retapable.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Station, $this> */
    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    /** @return HasMany<Itinerary, $this> */
    public function departures(): HasMany
    {
        return $this->hasMany(Itinerary::class, 'origin_city_id');
    }

    /** @return HasMany<Itinerary, $this> */
    public function arrivals(): HasMany
    {
        return $this->hasMany(Itinerary::class, 'destination_city_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'region', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
