<?php

declare(strict_types=1);

namespace App\Filament\Resources\ImportLogs\Tables;

use App\Enums\LogLevel;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('level')
                    ->badge(),
                TextColumn::make('event')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('importRun.original_filename')
                    ->label('File')
                    ->limit(30)
                    ->url(fn ($record) => $record->import_run_id
                        ? route('filament.admin.resources.import-runs.view', $record->import_run_id)
                        : null),
                TextColumn::make('message')
                    ->wrap()
                    ->limit(80)
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->options(LogLevel::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
