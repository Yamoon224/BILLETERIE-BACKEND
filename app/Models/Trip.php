<?php

namespace App\Models;

use App\Domains\Network\Support\SeatMap;
use App\Domains\Scheduling\Enums\TripStatus;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Depart : l'exemplaire vendable d'un itineraire, a une date et une heure.
 *
 * `price`, `seat_capacity` et `seats_per_row` sont des copies figees prises a
 * la creation. Voir la migration : ce que le voyageur a achete doit rester
 * lisible tel qu'il l'a achete, meme si le tarif de l'itineraire ou le bus
 * affecte changent ensuite.
 *
 * @property string $id
 * @property string $reference
 * @property string $company_id
 * @property string $itinerary_id
 * @property string $vehicle_id
 * @property string $departure_station_id
 * @property string $arrival_station_id
 * @property Carbon $departs_at
 * @property Carbon|null $arrives_at
 * @property int $price
 * @property int $seat_capacity
 * @property int $seats_per_row
 * @property TripStatus $status
 * @property string|null $cancellation_reason
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read Itinerary $itinerary
 * @property-read Vehicle $vehicle
 * @property-read Station $departureStation
 * @property-read Station $arrivalStation
 */
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'reference', 'company_id', 'itinerary_id', 'vehicle_id',
        'departure_station_id', 'arrival_station_id',
        'departs_at', 'arrives_at', 'price',
        'seat_capacity', 'seats_per_row', 'status',
        'cancellation_reason', 'cancelled_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'departs_at' => 'datetime',
            'arrives_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'price' => 'integer',
            'seat_capacity' => 'integer',
            'seats_per_row' => 'integer',
            'status' => TripStatus::class,
        ];
    }

    /**
     * Plan de salle du depart.
     *
     * Construit sur les copies figees du depart, jamais sur le vehicule
     * courant : un bus remplace la veille ne doit pas renumeroter les places
     * deja vendues.
     */
    public function seatMap(): SeatMap
    {
        return new SeatMap($this->seat_capacity, $this->seats_per_row);
    }

    /**
     * L'heure presente est-elle dans la fenetre d'embarquement ?
     *
     * Verifie ici plutot que dans le service de validation parce que la reponse
     * ne depend que du depart lui-meme, et que le guichet comme l'ecran de
     * controle ont besoin de la meme reponse.
     */
    public function isWithinBoardingWindow(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();
        $window = config('ticketing.boarding_window');

        return $now->between(
            $this->departs_at->copy()->subMinutes((int) $window['opens_minutes_before']),
            $this->departs_at->copy()->addMinutes((int) $window['closes_minutes_after']),
        );
    }

    /** Le depart est-il deja parti, du point de vue de l'horloge ? */
    public function hasLeft(?Carbon $now = null): bool
    {
        return ($now ?? Carbon::now())->greaterThan($this->departs_at);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Itinerary, $this> */
    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Station, $this> */
    public function departureStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'departure_station_id');
    }

    /** @return BelongsTo<Station, $this> */
    public function arrivalStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'arrival_station_id');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'reference', 'vehicle_id', 'departure_station_id', 'arrival_station_id',
                'departs_at', 'arrives_at', 'price', 'status', 'cancellation_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
