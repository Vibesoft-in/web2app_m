<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationCampaign;
use App\Models\PushDevice;
use App\Models\UsageEvent;
use App\Services\Notifications\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $campaignId
    ) {}

    public function handle(FcmService $fcmService): void
    {
        // Atomic status transition: prevents duplicate execution across multiple queue workers
        $updated = NotificationCampaign::withoutTenantScope()
            ->where('id', $this->campaignId)
            ->whereIn('status', ['pending', 'scheduled'])
            ->update(['status' => 'processing']);

        if ($updated === 0) {
            return;
        }

        $campaign = NotificationCampaign::withoutTenantScope()->find($this->campaignId);
        if (! $campaign) {
            return;
        }

        $app = $campaign->app;
        if (! $app) {
            $campaign->update(['status' => 'failed']);
            return;
        }

        $deepLink = $campaign->audience['deep_link'] ?? null;
        $totalSent = 0;

        // Query device tokens chunked by 500 to prevent memory spikes (FCM HTTP v1 batch cap)
        PushDevice::withoutTenantScope()
            ->where('app_id', $app->id)
            ->where('tenant_id', $app->tenant_id)
            ->select('fcm_token')
            ->chunk(500, function ($devices) use ($fcmService, $campaign, $deepLink, $app, &$totalSent) {
                $tokens = $devices->pluck('fcm_token')->filter()->values()->toArray();
                if (! empty($tokens)) {
                    $stats = $fcmService->sendBatch($tokens, $campaign->title, $campaign->body, $deepLink, $app);
                    $totalSent += $stats['sent'];
                }
            });

        // Record billable usage event for tenant
        if ($totalSent > 0) {
            UsageEvent::withoutTenantScope()->create([
                'tenant_id'  => $campaign->tenant_id,
                'app_id'     => $app->id,
                'type'       => 'push_send',
                'qty'        => $totalSent,
                'cost'       => 0.0,
                'created_at' => now(),
            ]);
        }

        $campaign->update([
            'status' => 'completed',
        ]);
    }
}
