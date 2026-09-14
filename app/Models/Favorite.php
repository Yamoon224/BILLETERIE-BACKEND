<?php

namespace App\Models;

use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Trajet favori d'un voyageur : un couple ville de depart / ville d'arrivee.
 *
 * Ne porte pas `LogsActivity` : contrairement a une reservation ou un
 * itineraire, ce n'est pas une entite operationnelle sur laquelle une
 * compagnie ou l'administrateur doivent pouvoir remonter — c'est une
 * preference personnelle, sans consequence a auditer.
 *
 * @property string $id
 * @property string $user_id
 * @property string $origin_city_id
 * @property string $destination_city_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read City $originCity
 * @property-read City $destinationCity
 */
class Favorite extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = ['user_id', 'origin_city_id', 'destination_city_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
