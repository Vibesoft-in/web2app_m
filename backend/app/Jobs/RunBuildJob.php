<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BuildStatus;
use App\Models\Build;
use App\Services\Build\Worker\BuildWorker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunBuildJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800; // 30 minutes
    public int $tries = 1;      // No blind retries; builds must fail cleanly

    public function __construct(
        public readonly int $buildId
    ) {}

    public function handle(BuildWorker $worker): void
    {
        $build = Build::withoutTenantScope()->find($this->buildId);
        if (! $build) {
            return;
        }

        $worker->execute($build);
    }

    public function failed(Throwable $exception): void
    {
        $build = Build::withoutTenantScope()->find($this->buildId);
        if ($build && ! $build->status->isTerminal()) {
            $build->update([
                'status'      => BuildStatus::Failed,
                'error'       => 'Build worker job execution timed out or crashed: '.$exception->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
