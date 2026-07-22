<?php

declare(strict_types=1);

namespace App\Filament\Resources\ImportLogs;

use App\Filament\Resources\ImportLogs\Pages\ListImportLogs;
use App\Filament\Resources\ImportLogs\Pages\ViewImportLog;
use App\Filament\Resources\ImportLogs\Schemas\ImportLogInfolist;
use App\Filament\Resources\ImportLogs\Tables\ImportLogsTable;
use App\Models\ImportLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImportLogResource extends Resource
{
    protected static ?string $model = ImportLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Import Logs';

    protected static ?string $modelLabel = 'import log';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return ImportLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportLogs::route('/'),
            'view' => ViewImportLog::route('/{record}'),
        ];
    }
}
