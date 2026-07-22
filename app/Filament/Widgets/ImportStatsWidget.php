<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ImportStatus;
use App\Enums\ProductImportStatus;
use App\Models\ImportedProduct;
use App\Models\ImportRun;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ImportStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Import runs', (string) ImportRun::count())
                ->description(ImportRun::where('status', ImportStatus::Completed)->count().' completed')
                ->color('primary'),

            Stat::make('Products imported', (string) ImportedProduct::where('status', ProductImportStatus::Success)->count())
                ->description('Created or updated in Shopify')
                ->color('success'),

            Stat::make('Failed rows', (string) ImportedProduct::where('status', ProductImportStatus::Failed)->count())
                ->description('Across all runs')
                ->color('danger'),
        ];
    }
}
