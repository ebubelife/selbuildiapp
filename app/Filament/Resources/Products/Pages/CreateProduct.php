<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Inventory;
use App\Models\ProductImage;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** @var array<int, string> */
    private array $imagePaths = [];

    private int $quantityAvailable = 0;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        $data['sku'] = 'SB-'.Str::upper(Str::random(8));

        // Neither 'images' nor 'quantity_available' are columns on
        // products - stash them and handle them in afterCreate() instead.
        $this->imagePaths = $data['images'] ?? [];
        unset($data['images']);

        $this->quantityAvailable = (int) ($data['quantity_available'] ?? 0);
        unset($data['quantity_available']);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->imagePaths as $sortOrder => $path) {
            ProductImage::create([
                'product_id' => $this->record->id,
                'path' => $path,
                'sort_order' => $sortOrder,
            ]);
        }

        $supplier = $this->record->supplierProfile;
        $warehouse = $supplier->warehouses()->first()
            ?? $supplier->warehouses()->create(['name' => $supplier->business_name.' - Main Warehouse']);

        Inventory::updateOrCreate(
            ['product_id' => $this->record->id, 'product_variant_id' => null, 'warehouse_id' => $warehouse->id],
            ['quantity_available' => $this->quantityAvailable]
        );
    }
}
