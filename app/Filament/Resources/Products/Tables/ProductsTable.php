<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Category;
use App\Models\SupplierProfile;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('supplierProfile.business_name')->label('Supplier')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('price')->numeric()->sortable()->suffix(' XAF'),
                ToggleColumn::make('is_active')->label('Active'),
                ToggleColumn::make('is_featured')->label('Featured'),
                TextColumn::make('created_at')->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('supplier_profile_id')
                    ->label('Supplier')
                    ->options(fn () => SupplierProfile::pluck('business_name', 'id')),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => Category::pluck('name', 'id')),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options(['1' => 'Active', '0' => 'Inactive']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
