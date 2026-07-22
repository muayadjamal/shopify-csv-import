<?php

declare(strict_types=1);

namespace App\Filament\Resources\ImportRuns\Tables;

use App\Enums\ImportStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('total_rows')
                    ->label('Rows')
                    ->numeric()
                    ->placeholder('—'),
                TextColumn::make('successful_rows')
                    ->label('Success')
                    ->badge()
                    ->color('success'),
                TextColumn::make('failed_rows')
                    ->label('Failed')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ImportStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
