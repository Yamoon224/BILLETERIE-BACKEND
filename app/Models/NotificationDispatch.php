<?php

namespace App\Models;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Envoi sortant (SMS, messagerie) et son sort.
 *
 * Pas de journal d'activite sur ce modele : il **est** deja un journal. Le
 * doubler d'entrees d'audit noierait les evenements qui comptent — une vente,
 * une annulation, un scan refuse — sous le trafic des notifications.
 *
 * @property string $id
 * @property string|null $booking_id
 * @property string|null $ticket_id
 * @property NotificationChannel $channel
 * @property string $recipient
 * @property string $template
 * @property array<string, mixed>|null $payload
 * @property NotificationStatus $status
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationDispatch extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'booking_id', 'ticket_id', 'channel', 'recipient', 'template',
        'payload', 'status', 'attempts', 'error', 'sent_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
