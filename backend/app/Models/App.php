<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppStatus;
use App\Enums\Platform;
use App\Enums\SetupStep;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class App extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'tenant_id',
        'name',
        'platform',
        'package_name',
        'app_key',
        'setup_step',
        'status',
        'firebase_project_id',
        'firebase_app_id',
    ];

    protected $casts = [
        'platform'   => Platform::class,
        'setup_step' => SetupStep::class,
        'status'     => AppStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (App $app) {
            if (empty($app->ulid)) {
                $app->ulid = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function connection(): HasOne
    {
        return $this->hasOne(Connection::class);
    }

    public function configs(): HasMany
    {
        return $this->hasMany(AppConfig::class);
    }

    public function builds(): HasMany
    {
        return $this->hasMany(Build::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function keystore(): HasOne
    {
        return $this->hasOne(Keystore::class);
    }

    public function appUsers(): HasMany
    {
        return $this->hasMany(AppUser::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function notificationCampaigns(): HasMany
    {
        return $this->hasMany(NotificationCampaign::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Determines if a new build can be triggered.
     * All conditions must pass (rule from docs §6).
     */
    public function isBuildAllowed(): bool
    {
        $connection = $this->connection()->first();

        return $connection !== null
            && $connection->status === \App\Enums\ConnectionStatus::Verified
            && $this->hasPublishedConfig()
            && $this->hasActiveSubscription();
    }

    public function hasPublishedConfig(): bool
    {
        return $this->configs()
            ->where('state', 'published')
            ->exists();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('current_period_end', '>', now())
            ->exists();
    }

    /**
     * @return array<string> List of blockers preventing app build
     */
    public function getBuildBlockers(): array
    {
        $blockers = [];
        $connection = $this->connection()->first();

        if (! $connection || $connection->status !== \App\Enums\ConnectionStatus::Verified) {
            $blockers[] = 'CONNECTION_NOT_VERIFIED';
        }

        if (! $this->hasPublishedConfig()) {
            $blockers[] = 'CONFIG_NOT_PUBLISHED';
        }

        if (! $this->hasActiveSubscription()) {
            $blockers[] = 'SUBSCRIPTION_NOT_ACTIVE';
        }

        return $blockers;
    }
}
