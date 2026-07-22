<?php

declare(strict_types=1);

namespace App\Filament\Resources\ImportLogs\Pages;

use App\Filament\Resources\ImportLogs\ImportLogResource;
use Filament\Resources\Pages\ListRecords;

class ListImportLogs extends ListRecords
{
    protected static string $resource = ImportLogResource::class;
}
