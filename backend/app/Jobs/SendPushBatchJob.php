<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\App;
use App\Services\Notifications\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string> $tokens
     */
    public function __construct(
        public readonly array $tokens,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $deepLink = null,
        public readonly ?int $appId = null
    ) {}

    public function handle(FcmService $fcmService): void
    {
        $app = $this->appId ? App::withoutTenantScope()->find($this->appId) : null;
        $fcmService->sendBatch($this->tokens, $this->title, $this->body, $this->deepLink, $app);
    }
}
