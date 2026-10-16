<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;

class ImpersonationService
{
    private const SESSION_IMPERSONATOR = 'impersonator_user_id';
    private const SESSION_TENANT = 'impersonated_tenant_id';

    /**
     * Starts an impersonation session with an audit log trail.
     */
    public function startImpersonation(User $actor, Tenant $targetTenant): void
    {
        session([
            self::SESSION_IMPERSONATOR => $actor->id,
            self::SESSION_TENANT       => $targetTenant->id,
        ]);

        AuditLog::create([
            'actor_id'   => $actor->id,
            'tenant_id'  => $targetTenant->id,
            'action'     => 'impersonation.started',
            'target'     => "Tenant:{$targetTenant->id}",
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta'       => [
                'actor_email'  => $actor->email,
                'tenant_name'  => $targetTenant->name,
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * Stops the active impersonation session and logs the exit.
     */
    public function stopImpersonation(): void
    {
        $actorId = session(self::SESSION_IMPERSONATOR);
        $tenantId = session(self::SESSION_TENANT);

        if ($actorId && $tenantId) {
            AuditLog::create([
                'actor_id'   => $actorId,
                'tenant_id'  => $tenantId,
                'action'     => 'impersonation.stopped',
                'target'     => "Tenant:{$tenantId}",
                'ip'         => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        }

        session()->forget([self::SESSION_IMPERSONATOR, self::SESSION_TENANT]);
    }

    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_IMPERSONATOR);
    }

    public function getImpersonatedTenantId(): ?int
    {
        return session(self::SESSION_TENANT);
    }

    public function getImpersonatorId(): ?int
    {
        return session(self::SESSION_IMPERSONATOR);
    }
}
