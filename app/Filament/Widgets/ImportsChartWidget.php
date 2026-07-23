<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ProductImportStatus;
use App\Models\ImportedProduct;
use Carbon\CarbonInterface;
use Filament\Widgets\ChartWidget;

class ImportsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Products imported (last 7 days)';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $days = collect(range(6, 0))->map(fn (int $i) => now()->subDays($i));

        $countBy = fn (ProductImportStatus $status, CarbonInterface $day): int => ImportedProduct::query()
            ->where('status', $status)
            ->whereDate('created_at', $day)
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Successful',
                    'data' => $days->map(fn ($day) => $countBy(ProductImportStatus::Success, $day))->all(),
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#10b981',
                ],
                [
                    'label' => 'Failed',
                    'data' => $days->map(fn ($day) => $countBy(ProductImportStatus::Failed, $day))->all(),
                    'backgroundColor' => '#ef4444',
                    'borderColor' => '#ef4444',
                ],
            ],
            'labels' => $days->map(fn ($day) => $day->format('M j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
