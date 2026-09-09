<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Inventory;
use App\Models\ProductImage;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** @var array<int, string> */
    private array $imagePaths = [];

    private int $quantityAvailable = 0;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Existing photos are shown/removed via the separate gallery
        // (see product-images-manager.blade.php) - this field stays
        // empty on load and is only ever for photos being added now.
        $data['quantity_available'] = $this->record->inventories()->sum('quantity_available');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->imagePaths = $data['images'] ?? [];
        unset($data['images']);

        $this->quantityAvailable = (int) ($data['quantity_available'] ?? 0);
        unset($data['quantity_available']);

        return $data;
    }

    /**
     * Deletes a single existing photo right away - deliberately not tied
     * to the form's Save button, so removing a photo can't be lost by
     * navigating away, and there's no risk of it conflicting with
     * whatever's mid-upload in the "Add Photos" field.
     */
    public function removeExistingImage(int $imageId): void
    {
        $image = $this->record->images()->findOrFail($imageId);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        // Force the gallery partial to re-query instead of showing the
        // now-stale cached relation on this same render.
        $this->record->unsetRelation('images');

        Notification::make()->title('Photo removed')->success()->send();
    }

    protected function afterSave(): void
    {
        $nextSortOrder = ($this->record->images()->max('sort_order') ?? -1) + 1;

        foreach ($this->imagePaths as $path) {
            ProductImage::create([
                'product_id' => $this->record->id,
                'path' => $path,
                'sort_order' => $nextSortOrder++,
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
