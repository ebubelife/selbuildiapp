<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductImage;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** @var array<int, string> */
    private array $imagePaths = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // 'images' isn't a column on products - fill the field from the
        // real images() relation so the admin sees the existing gallery.
        $data['images'] = $this->record->images()->orderBy('sort_order')->pluck('path')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->imagePaths = $data['images'] ?? [];
        unset($data['images']);

        return $data;
    }

    /**
     * Reconciles the product's images() relation against whatever paths
     * are left in the form: anything removed from the gallery gets
     * deleted (row + file), anything new gets a ProductImage row, and
     * order is rewritten to match the field's (reorderable) order.
     */
    protected function afterSave(): void
    {
        $existing = $this->record->images()->get()->keyBy('path');
        $keptPaths = [];

        foreach ($this->imagePaths as $sortOrder => $path) {
            if ($existing->has($path)) {
                $existing->get($path)->update(['sort_order' => $sortOrder]);
            } else {
                ProductImage::create([
                    'product_id' => $this->record->id,
                    'path' => $path,
                    'sort_order' => $sortOrder,
                ]);
            }

            $keptPaths[] = $path;
        }

        // Eloquent Collection::except() filters by primary key, not by
        // whatever keyBy() was used to re-key the collection - it would
        // silently never match these path-string keys, so this needs an
        // explicit filter instead.
        $removed = $existing->reject(fn ($image, $path) => in_array($path, $keptPaths, true));

        foreach ($removed as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
    }
}
