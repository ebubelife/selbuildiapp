<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Brand;
use App\Models\Category;
use App\Models\SupplierProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('supplier_profile_id')
                    ->label('Supplier')
                    ->options(fn () => SupplierProfile::pluck('business_name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('category_id')
                    ->label('Category')
                    ->options(fn () => Category::pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Select::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => Brand::pluck('name', 'id'))
                    ->searchable(),
                Select::make('unit')
                    ->options(array_combine(
                        ['bag', 'ton', 'piece', 'meter', 'liter', 'roll'],
                        ['Bag', 'Ton', 'Piece', 'Meter', 'Liter', 'Roll'],
                    ))
                    ->required(),
                TextInput::make('price')->numeric()->required()->suffix('XAF'),
                TextInput::make('compare_at_price')->numeric()->suffix('XAF'),
                TextInput::make('min_order_qty')->numeric()->required()->default(1),
                Textarea::make('description')->columnSpanFull(),
                Toggle::make('is_active')->default(true),
                Toggle::make('is_featured'),
            ]);
    }
}
