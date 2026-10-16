<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Automatically scopes Eloquent queries to the current tenant and
 * sets tenant_id on model creation.
 *
 * Security contract:
 *   - tenant_id is ALWAYS derived from the authenticated context (Auth::user()).
 *   - It is NEVER accepted from request input.
 *   - Cross-tenant access returns 404 via route model binding scope.
 *
 * Usage: add `use BelongsToTenant;` to any model that has a tenant_id column.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Auto-set tenant_id from auth context on creation.
        // If no authenticated user exists (e.g. seeder/job), caller must set it manually.
        static::creating(function (Model $model) {
            if (! $model->isDirty('tenant_id')) {
                $tenantId = static::resolveTenantId();
                if ($tenantId !== null) {
                    $model->tenant_id = $tenantId;
                }
            }
        });

        // Apply global scope so every query is automatically tenant-scoped.
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = static::resolveTenantId();

            if ($tenantId !== null) {
                $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });
    }

    /**
     * Resolve the current tenant ID from the authenticated user.
     * Returns null when running in CLI/job context without a bound tenant.
     */
    protected static function resolveTenantId(): ?int
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        // When inside Filament admin panel, cross-tenant management across all apps and users is intentional
        if (request()?->is('admin*') || (class_exists(\Filament\Facades\Filament::class) && \Filament\Facades\Filament::getCurrentPanel() !== null)) {
            return null;
        }

        return Auth::user()?->tenant_id ?? request()?->user()?->tenant_id; // @phpstan-ignore-line
    }

    /**
     * Convenience: query without the global tenant scope.
     * USE ONLY in jobs/admin code where cross-tenant access is intentional.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }

    /** Tenant relationship. */
    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
