<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\View;
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
                // Existing photos are managed separately below (instant
                // remove, no FileUpload preview involved) - this field is
                // only ever for photos being added right now. Pre-filling
                // a multi-image FileUpload with already-stored paths is
                // what was causing the preview to spin forever on edit;
                // keeping it upload-only sidesteps that entirely, and is
                // also just a clearer interaction: "add photos" here,
                // "remove photos" in the gallery below.
                View::make('filament.product-images-manager')
                    ->viewData(fn (?Product $record) => ['images' => $record?->images ?? collect()])
                    ->columnSpanFull(),
                // Not a real column on products - CreateProduct/EditProduct
                // pull the uploaded paths out of the form data and create
                // ProductImage rows for them themselves.
                FileUpload::make('images')
                    ->label('Add Photos')
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
