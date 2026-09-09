<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductImage;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** @var array<int, string> */
    private array $imagePaths = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        $data['sku'] = 'SB-'.Str::upper(Str::random(8));

        // 'images' isn't a column on products - stash the uploaded paths
        // and create the actual ProductImage rows in afterCreate() instead.
        $this->imagePaths = $data['images'] ?? [];
        unset($data['images']);

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
    }
}
