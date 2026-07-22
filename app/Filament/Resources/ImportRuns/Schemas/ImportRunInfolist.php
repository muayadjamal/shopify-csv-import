<?php

declare(strict_types=1);

namespace App\Filament\Resources\ImportRuns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Import')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('original_filename')
                            ->label('File')
                            ->columnSpan(2),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('total_rows')
                            ->label('Total rows')
                            ->placeholder('—'),
                        TextEntry::make('successful_rows')
                            ->label('Successful')
                            ->badge()
                            ->color('success'),
                        TextEntry::make('failed_rows')
                            ->label('Failed')
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                    ]),
                Section::make('Timestamps')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('started_at')->dateTime()->placeholder('—'),
                        TextEntry::make('completed_at')->dateTime()->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
                Section::make('Error')
                    ->visible(fn ($record): bool => filled($record->error_message))
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('')
                            ->color('danger'),
                    ]),
            ]);
    }
}
