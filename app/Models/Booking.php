<?php

namespace App\Models;

use App\Domains\Booking\Enums\BookingChannel;
use App\Domains\Booking\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Reservation : un acte d'achat, en ligne ou au guichet.
 *
 * @property string $id
 * @property string $reference
 * @property string $trip_id
 * @property string $company_id
 * @property BookingChannel $channel
 * @property BookingStatus $status
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $customer_email
 * @property string|null $customer_user_id
 * @property string|null $sold_by_user_id
 * @property string|null $station_id
 * @property int $seats_count
 * @property int $total_amount
 * @property string $currency
 * @property int $commission_amount
 * @property int $commission_per_mille
 * @property Carbon|null $expires_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $client_reference
 * @property Carbon|null $sold_offline_at
 * @property Carbon|null $synced_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Trip $trip
 * @property-read Company $company
 * @property-read User|null $soldBy
 * @property-read User|null $customer
 * @property-read Station|null $station
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'reference', 'trip_id', 'company_id', 'channel', 'status',
        'customer_name', 'customer_phone', 'customer_email', 'customer_user_id',
        'sold_by_user_id', 'station_id', 'seats_count', 'total_amount', 'currency',
        'commission_amount', 'commission_per_mille',
        'expires_at', 'confirmed_at', 'cancelled_at', 'cancellation_reason',
        'client_reference', 'sold_offline_at', 'synced_at', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => BookingChannel::class,
            'status' => BookingStatus::class,
            'seats_count' => 'integer',
            'total_amount' => 'integer',
            'commission_amount' => 'integer',
            'commission_per_mille' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'sold_offline_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Le blocage de places est-il perime ?
     *
     * Repond a la question « cette reservation devrait-elle deja avoir libere
     * ses places », independamment du fait que la commande d'expiration soit
     * passee ou non. Les deux ne coincident jamais parfaitement : la commande
     * tourne par lots, et un voyageur peut consulter sa reservation entre deux
     * passages. Lui afficher « en attente de paiement » alors que le delai est
     * ecoule reviendrait a lui laisser payer une place qu'il n'a plus.
     */
    public function isHoldExpired(?Carbon $now = null): bool
    {
        if ($this->status !== BookingStatus::Pending || $this->expires_at === null) {
            return false;
        }

        return ($now ?? Carbon::now())->greaterThan($this->expires_at);
    }

    /** Montant revenant a la compagnie, commission plateforme deduite. */
    public function netAmount(): int
    {
        return $this->total_amount - $this->commission_amount;
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by_user_id');
    }

    /** @return BelongsTo<Station, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'reference', 'trip_id', 'status', 'channel', 'seats_count',
                'total_amount', 'commission_amount', 'cancellation_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
