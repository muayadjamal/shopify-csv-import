<?php

declare(strict_types=1);

namespace App\Filament\Resources\ImportRuns\RelationManagers;

use App\Enums\ProductImportStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Rows';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('row_number')
            ->columns([
                TextColumn::make('row_number')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('shopify_product_id')
                    ->label('Shopify ID')
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->color('danger')
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ProductImportStatus::class),
            ]);
    }
}
