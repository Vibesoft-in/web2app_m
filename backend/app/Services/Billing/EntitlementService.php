<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\App;
use App\Models\Build;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UsageEvent;

class EntitlementService
{
    private const GRACE_PERIOD_DAYS = 7;

    /**
     * Checks if a tenant is entitled to a specific feature flag.
     * Evaluates active subscription, grace period (7 days), and plan feature JSON.
     */
    public function can(Tenant $tenant, string $feature): bool
    {
        $subscription = Subscription::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->first();

        if (! $subscription) {
            return false;
        }

        $now = now();
        $periodEnd = $subscription->current_period_end;

        // Check if subscription has expired
        if ($periodEnd && $periodEnd->isPast()) {
            $gracePeriodEnds = $periodEnd->copy()->addDays(self::GRACE_PERIOD_DAYS);

            // Outside grace period: complete lockout
            if ($now->isAfter($gracePeriodEnds)) {
                return false;
            }

            // Inside 7-day grace period: lock premium add-ons (whatsapp, extra builds), permit basic app
            if (in_array(strtolower($feature), ['whatsapp', 'push_broadcast', 'custom_domain'], true)) {
                return false;
            }
        }

        if ($subscription->status !== 'active') {
            return false;
        }

        $plan = $subscription->plan;
        if (! $plan) {
            return false;
        }

        $features = $plan->features ?? [];

        return (bool) ($features[$feature] ?? false);
    }

    /**
     * Checks whether a tenant has remaining quota for a given monthly limit (builds, push, apps).
     */
    public function hasRemainingLimit(Tenant $tenant, string $limitKey): bool
    {
        $subscription = Subscription::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $subscription || ! $subscription->plan) {
            return false;
        }

        $limits = $subscription->plan->limits ?? [];
        $maxLimit = (int) ($limits[$limitKey] ?? 0);

        if ($maxLimit <= 0) {
            return true; // Unlimited if set to 0 or unconfigured
        }

        $startOfMonth = now()->startOfMonth();

        return match ($limitKey) {
            'builds_per_month' => Build::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('created_at', '>=', $startOfMonth)
                ->count() < $maxLimit,

            'push_per_month' => (int) UsageEvent::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('type', 'push_send')
                ->where('created_at', '>=', $startOfMonth)
                ->sum('qty') < $maxLimit,

            'max_apps' => App::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->count() < $maxLimit,

            default => true,
        };
    }

    /**
     * Records an immutable usage event for auditing, billing rollup, and limit tracking.
     */
    public function recordUsage(
        Tenant $tenant,
        ?App $app,
        string $type,
        int $qty = 1,
        float $cost = 0.0
    ): UsageEvent {
        return UsageEvent::withoutTenantScope()->create([
            'tenant_id'  => $tenant->id,
            'app_id'     => $app?->id,
            'type'       => $type,
            'qty'        => $qty,
            'cost'       => $cost,
            'created_at' => now(),
        ]);
    }
}
