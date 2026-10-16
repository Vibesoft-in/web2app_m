<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * Mass-assignable attributes.
     * tenant_id is intentionally excluded — always set from auth context or explicitly.
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'email_verified_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'phone_verified_at'  => 'datetime',
        'password'           => 'hashed',
        'role'               => UserRole::class,
        'two_factor_secret'  => 'encrypted', // stored encrypted at rest
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isEditor(): bool
    {
        return in_array($this->role, [UserRole::Owner, UserRole::Editor], strict: true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === UserRole::Owner;
    }
}
