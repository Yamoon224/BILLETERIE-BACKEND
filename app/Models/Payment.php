<?php

namespace App\Models;

use App\Domains\Payments\Enums\MobileMoneyProvider;
use App\Domains\Payments\Enums\PaymentMethod;
use App\Domains\Payments\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Encaissement.
 *
 * Aucune donnee de paiement n'est stockee ici : ni numero de carte, ni jeton
 * reutilisable, ni secret d'operateur. Ce que la table conserve — montant,
 * moyen, statut, reference externe — permet de rapprocher une recette, jamais
 * de rejouer un debit.
 *
 * @property string $id
 * @property string $reference
 * @property string $booking_id
 * @property PaymentMethod $method
 * @property MobileMoneyProvider|null $provider
 * @property string $gateway
 * @property int $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property string|null $external_reference
 * @property string|null $payer_msisdn
 * @property string|null $collected_by_user_id
 * @property array<string, mixed>|null $gateway_payload
 * @property string|null $failure_reason
 * @property Carbon|null $authorized_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $refunded_at
 * @property string|null $client_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Booking $booking
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'reference', 'booking_id', 'method', 'provider', 'gateway',
        'amount', 'currency', 'status', 'external_reference', 'payer_msisdn',
        'collected_by_user_id', 'gateway_payload', 'failure_reason',
        'authorized_at', 'paid_at', 'failed_at', 'refunded_at', 'client_reference',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'provider' => MobileMoneyProvider::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'gateway_payload' => 'array',
            'authorized_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // La charge utile de l'agregateur n'est pas journalisee : elle est
            // volumineuse, deja conservee dans la colonne, et un journal
            // d'audit lu par le support n'a pas a la recopier a chaque
            // changement de statut.
            ->logOnly(['reference', 'method', 'provider', 'amount', 'status', 'external_reference', 'failure_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
