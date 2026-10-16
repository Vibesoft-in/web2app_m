<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectionStatus: string
{
    case NotStarted = 'not_started';
    case Configured = 'configured';
    case Verified   = 'verified';
    case Failed     = 'failed';
}
