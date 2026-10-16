<?php

declare(strict_types=1);

namespace App\Enums;

enum BuildStatus: string
{
    case Queued    = 'queued';
    case Preparing = 'preparing';
    case Building  = 'building';
    case Signing   = 'signing';
    case Ready     = 'ready';
    case Failed    = 'failed';
    case TimedOut  = 'timed_out';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Ready, self::Failed, self::TimedOut => true,
            default                                    => false,
        };
    }
}
