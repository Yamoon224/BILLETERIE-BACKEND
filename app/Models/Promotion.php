<?php

namespace App\Models;

use App\Domains\Promotions\Enums\PromotionKind;
use App\Domains\Promotions\Enums\PromotionZone;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Banniere ou tuile « A la une » de la page d'accueil.
 *
 * @property string $id
 * @property string $title
 * @property string|null $subtitle
 * @property PromotionZone $zone
 * @property PromotionKind $kind
 * @property string|null $advertiser_name
 * @property string|null $partner_id
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Partner|null $partner
 * @property-read User|null $creator
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'title', 'subtitle', 'zone', 'kind', 'advertiser_name', 'partner_id',
        'starts_at', 'ends_at', 'is_active', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'zone' => PromotionZone::class,
            'kind' => PromotionKind::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'zone', 'kind', 'advertiser_name', 'starts_at', 'ends_at', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
