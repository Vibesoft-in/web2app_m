<?php

declare(strict_types=1);

namespace App\Filament\Resources\UsageEventResource\Pages;

use App\Filament\Resources\UsageEventResource;
use Filament\Resources\Pages\ListRecords;

class ListUsageEvents extends ListRecords
{
    protected static string $resource = UsageEventResource::class;
}
