<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Brand;
use App\Models\Category;
use App\Models\SupplierProfile;
use Filament\Forms\Components\FileUpload;
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
                // Not a real column on products - CreateProduct/EditProduct
                // pull this out of the form data and upsert it onto the
                // supplier's Inventory record themselves (same as the
                // supplier-facing form). Without this, a product created
                // here would always show "Out of Stock" - stock lives on
                // Inventory, not on the product itself.
                TextInput::make('quantity_available')
                    ->label('Stock Quantity')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->helperText('How many units are available right now.'),
                Textarea::make('description')->columnSpanFull(),
                Textarea::make('specification')
                    ->label('Specification')
                    ->helperText('Size, grade, material - e.g. "12mm, Grade 60, 12m length".')
                    ->columnSpanFull(),
                // Not a real column on products - CreateProduct/EditProduct
                // pull this out of the form data and reconcile it against
                // the product's images() relation themselves, the same way
                // the supplier-facing product form already handles
                // multiple photos.
                FileUpload::make('images')
                    ->label('Product Photos')
                    ->multiple()
                    ->image()
                    ->disk('public')
                    ->directory('product-images')
                    ->reorderable()
                    ->columnSpanFull(),
                Toggle::make('is_active')->default(true),
                Toggle::make('is_featured'),
            ]);
    }
}
