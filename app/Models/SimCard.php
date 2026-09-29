<?php

namespace App\Models;

use App\Domains\Sms\Enums\SimOperator;
use Database\Factories\SimCardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Carte SIM du SMS Box qui porte l'envoi des billets electroniques.
 *
 * @property string $id
 * @property SimOperator $operator
 * @property string|null $phone_number
 * @property int $balance
 * @property int $low_balance_threshold
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SimCard extends Model
{
    /** @use HasFactory<SimCardFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = ['operator', 'phone_number', 'balance', 'low_balance_threshold', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'operator' => SimOperator::class,
            'balance' => 'integer',
            'low_balance_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Une carte active dont le solde est passe sous le seuil d'alerte.
     *
     * Distinct de `is_active` : une carte peut etre volontairement mise hors
     * service (`is_active = false`) sans que son solde soit bas, et
     * inversement une carte active peut s'assecher en pleine journee de
     * vente sans que personne ne l'ait desactivee.
     */
    public function isLowBalance(): bool
    {
        return $this->is_active && $this->balance < $this->low_balance_threshold;
    }

    /** @return HasMany<NotificationDispatch, $this> */
    public function dispatches(): HasMany
    {
        return $this->hasMany(NotificationDispatch::class);
    }
}
