<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\App;
use App\Models\AppUser;
use App\Models\PushDevice;
use App\Services\Notifications\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderStatusPushJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $appId,
        public readonly string $storeCustomerId,
        public readonly string $orderId,
        public readonly string $status
    ) {}

    public function handle(FcmService $fcmService): void
    {
        $app = App::withoutTenantScope()->find($this->appId);
        if (! $app) {
            return;
        }

        // 1. Locate customer by store_customer_id
        $appUser = AppUser::withoutTenantScope()
            ->where('app_id', $app->id)
            ->where('store_customer_id', $this->storeCustomerId)
            ->first();

        if (! $appUser) {
            return;
        }

        // 2. Fetch device tokens registered to this customer (scoped strictly to app and tenant)
        $tokens = PushDevice::withoutTenantScope()
            ->where('tenant_id', $app->tenant_id)
            ->where('app_id', $app->id)
            ->where('app_user_id', $appUser->id)
            ->pluck('fcm_token')
            ->filter()
            ->values()
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $formattedStatus = ucfirst(str_replace(['_', '-'], ' ', $this->status));
        $title = "Order #{$this->orderId} Update";
        $body = "Your order status is now: {$formattedStatus}. Tap to view tracking.";
        $deepLink = "order:{$this->orderId}";

        $fcmService->sendBatch($tokens, $title, $body, $deepLink, $app);
    }
}
