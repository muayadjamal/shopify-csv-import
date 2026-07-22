<?php

declare(strict_types=1);

namespace App\Filament\Resources\ImportLogs\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->label('Time')->dateTime(),
                        TextEntry::make('level')->badge(),
                        TextEntry::make('event')->badge()->color('gray'),
                        TextEntry::make('importRun.original_filename')->label('File'),
                        TextEntry::make('message')->columnSpanFull(),
                        KeyValueEntry::make('context')->columnSpanFull(),
                    ]),
            ]);
    }
}
