<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * Compte utilisateur, tous roles confondus : voyageur, agent de vente,
 * gestionnaire de compagnie, administrateur de plateforme.
 *
 * Une seule table plutot que quatre : ce sont les memes colonnes, la meme
 * authentification et le meme cycle de vie. Ce qui les distingue est un role,
 * et un role n'est pas une structure de donnees differente.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $company_id
 * @property string|null $partner_id
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 * @property-read Partner|null $partner
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, LogsActivity, Notifiable;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'phone', 'password', 'company_id', 'partner_id', 'is_active'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** Ventes encaissees par cet utilisateur au guichet.
     *
     * @return HasMany<Booking, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Booking::class, 'sold_by_user_id');
    }

    /** Reservations passees en ligne par ce voyageur.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // Le mot de passe n'apparait jamais dans le journal, meme haché :
            // un journal d'audit est lu par plus de monde qu'une table de
            // comptes.
            ->logOnly(['name', 'email', 'phone', 'company_id', 'partner_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
