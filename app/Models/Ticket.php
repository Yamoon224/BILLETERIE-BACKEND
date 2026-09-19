<?php

namespace App\Models;

use App\Domains\Ticketing\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Billet : une place, un voyageur, un QR code.
 *
 * @property string $id
 * @property string $booking_id
 * @property string $trip_id
 * @property string $code
 * @property string|null $seat_number
 * @property string|null $released_seat_number
 * @property string $passenger_name
 * @property string|null $passenger_phone
 * @property string|null $passenger_id_number
 * @property TicketStatus $status
 * @property string $signature
 * @property int $key_version
 * @property Carbon|null $scanned_at
 * @property string|null $scanned_by_user_id
 * @property string|null $scanned_station_id
 * @property string|null $scan_client_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Booking $booking
 * @property-read Trip $trip
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'booking_id', 'trip_id', 'code', 'seat_number', 'released_seat_number',
        'passenger_name', 'passenger_phone', 'passenger_id_number', 'status', 'signature', 'key_version',
        'scanned_at', 'scanned_by_user_id', 'scanned_station_id', 'scan_client_reference',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'key_version' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * Ce billet a-t-il deja ete consomme ?
     *
     * Lue sur `scanned_at` et non sur le statut : les deux sont ecrits dans la
     * meme transaction, mais l'horodatage est la donnee primaire — c'est lui
     * qui dit *quand*, et un statut peut etre reecrit par une correction
     * ulterieure sans que l'embarquement, lui, ait ete annule.
     */
    public function isScanned(): bool
    {
        return $this->scanned_at !== null;
    }

    /** Numero de place affichable, y compris apres liberation. */
    public function displaySeatNumber(): ?string
    {
        return $this->seat_number ?? $this->released_seat_number;
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return BelongsTo<User, $this> */
    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    /** @return BelongsTo<Station, $this> */
    public function scannedStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'scanned_station_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'seat_number', 'released_seat_number', 'status', 'scanned_at', 'scanned_by_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
