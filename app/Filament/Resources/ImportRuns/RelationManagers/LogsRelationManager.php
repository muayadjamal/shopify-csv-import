<?php

declare(strict_types=1);

namespace App\Filament\Resources\ImportRuns\RelationManagers;

use App\Enums\LogLevel;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Log';

    public function table(Table $table): Table
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
                TextColumn::make('message')
                    ->wrap()
                    ->limit(100)
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->options(LogLevel::class),
            ]);
    }
}
