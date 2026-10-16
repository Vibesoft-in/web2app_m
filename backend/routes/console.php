<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Production Scheduler Tasks ────────────────────────────────────────────────

// 1. Mark stuck / runaway builds as TimedOut every 5 minutes (timeout threshold: 45 minutes)
Schedule::call(function () {
    $stuckThreshold = now()->subMinutes(45);
    $stuckBuilds = \App\Models\Build::whereIn('status', [
            \App\Enums\BuildStatus::Queued,
            \App\Enums\BuildStatus::Preparing,
            \App\Enums\BuildStatus::Building,
            \App\Enums\BuildStatus::Signing,
        ])
        ->where(function ($query) use ($stuckThreshold) {
            $query->where('started_at', '<=', $stuckThreshold)
                ->orWhere(function ($q) use ($stuckThreshold) {
                    $q->whereNull('started_at')->where('created_at', '<=', $stuckThreshold);
                });
        })
        ->get();

    foreach ($stuckBuilds as $build) {
        $build->update([
            'status'      => \App\Enums\BuildStatus::TimedOut,
            'finished_at' => now(),
            'error'       => 'Build process exceeded maximum runtime limit (45 minutes).',
        ]);
        \Illuminate\Support\Facades\Log::channel('daily')->warning("Stuck build #{$build->id} for app #{$build->app_id} marked as TimedOut.");
    }
})->everyFiveMinutes()->name('reap-stuck-builds')->withoutOverlapping();

// 2. Daily subscription expiry rollup
Schedule::call(function () {
    $expired = \App\Models\Subscription::where('status', 'active')
        ->where('current_period_end', '<', now())
        ->update(['status' => 'past_due']);

    if ($expired > 0) {
        \Illuminate\Support\Facades\Log::channel('daily')->info("Subscription rollup: {$expired} expired subscriptions updated to past_due.");
    }
})->dailyAt('00:05')->name('subscription-expiry-check')->withoutOverlapping();

// 3. Hourly connection health check alert
Schedule::call(function () {
    $failedCount = \App\Models\Connection::where('status', 'error')->count();
    if ($failedCount > 0) {
        \Illuminate\Support\Facades\Log::channel('daily')->warning("Connection Health: {$failedCount} store connections are in error state.");
    }
})->hourly()->name('connection-health-probe')->withoutOverlapping();

// 4. Prune expired Sanctum personal access tokens daily
Schedule::command('sanctum:prune-expired --hours=48')->daily()->name('prune-expired-tokens');

