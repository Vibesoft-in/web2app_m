<?php

declare(strict_types=1);

namespace App\Filament\Resources\BuildResource\Pages;

use App\Filament\Resources\BuildResource;
use Filament\Resources\Pages\ListRecords;

class ListBuilds extends ListRecords
{
    protected static string $resource = BuildResource::class;
}
