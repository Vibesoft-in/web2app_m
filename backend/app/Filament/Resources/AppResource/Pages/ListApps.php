<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppResource\Pages;

use App\Filament\Resources\AppResource;
use Filament\Resources\Pages\ListRecords;

class ListApps extends ListRecords
{
    protected static string $resource = AppResource::class;
}
