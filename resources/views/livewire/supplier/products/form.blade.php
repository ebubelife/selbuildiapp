<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.site', ['noindex' => true])] class extends Component
{
    use WithFileUploads;

    public ?Product $product = null;

    public string $name = '';
    public ?int $category_id = null;
    public ?int $brand_id = null;
    public string $description = '';
    public string $specification = '';
    public string $unit = 'bag';
    public ?int $price = null;
    public ?int $compare_at_price = null;
    public int $min_order_qty = 1;
    public ?float $weight_kg = null;
    public int $quantity_available = 0;

    /** @var array<int, mixed> */
    public array $images = [];

    public function mount(?Product $product = null): void
    {
        $supplier = Auth::user()->supplierProfile;

        abort_unless($supplier?->isVerified(), 403);

        if ($product) {
            abort_unless($product->supplier_profile_id === $supplier->id, 403);

            $this->product = $product;
            $this->name = $product->name;
            $this->category_id = $product->category_id;
            $this->brand_id = $product->brand_id;
            $this->description = (string) $product->description;
            $this->specification = (string) $product->specification;
            $this->unit = $product->unit;
            $this->price = $product->price;
            $this->compare_at_price = $product->compare_at_price;
            $this->min_order_qty = $product->min_order_qty;
            $this->weight_kg = $product->weight_kg;
            $this->quantity_available = $product->inventories->sum('quantity_available');
        }
    }

    public function removeImage(int $imageId): void
    {
        $image = ProductImage::where('product_id', $this->product?->id)->findOrFail($imageId);
        Storage::disk('public')->delete($image->path);
        $image->delete();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'description' => ['nullable', 'string'],
            'specification' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', 'in:bag,ton,piece,meter,liter,roll'],
            'price' => ['required', 'integer', 'min:1'],
            'compare_at_price' => ['nullable', 'integer', 'min:1'],
            'min_order_qty' => ['required', 'integer', 'min:1'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'quantity_available' => ['required', 'integer', 'min:0'],
            'images.*' => ['nullable', 'image', 'max:4096'],
        ]);

        $supplier = Auth::user()->supplierProfile;

        if ($this->product) {
            $this->product->update([
                'name' => $validated['name'],
                'category_id' => $validated['category_id'],
                'brand_id' => $validated['brand_id'],
                'description' => $validated['description'],
                'specification' => $validated['specification'],
                'unit' => $validated['unit'],
                'price' => $validated['price'],
                'compare_at_price' => $validated['compare_at_price'],
                'min_order_qty' => $validated['min_order_qty'],
                'weight_kg' => $validated['weight_kg'],
            ]);
            $product = $this->product;
        } else {
            $product = $supplier->products()->create([
                'category_id' => $validated['category_id'],
                'brand_id' => $validated['brand_id'],
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(6)),
                'sku' => 'SB-'.Str::upper(Str::random(8)),
                'description' => $validated['description'],
                'specification' => $validated['specification'],
                'unit' => $validated['unit'],
                'price' => $validated['price'],
                'compare_at_price' => $validated['compare_at_price'],
                'min_order_qty' => $validated['min_order_qty'],
                'weight_kg' => $validated['weight_kg'],
                'is_active' => true,
            ]);
        }

        foreach ($this->images as $image) {
            $path = $image->store('product-images', 'public');
            ProductImage::create(['product_id' => $product->id, 'path' => $path]);
        }

        $warehouse = $supplier->warehouses()->first()
            ?? $supplier->warehouses()->create(['name' => $supplier->business_name.' - Main Warehouse']);

        Inventory::updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => null, 'warehouse_id' => $warehouse->id],
            ['quantity_available' => $validated['quantity_available']]
        );

        $this->redirect(route('supplier.products.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <section class="pt-32 pb-10 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-3xl mx-auto px-6 lg:px-8">
            <a href="{{ route('supplier.products.index') }}" wire:navigate class="text-navy-300 hover:text-white text-sm flex items-center gap-1 mb-4 transition-colors">
                &larr; My Products
            </a>
            <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">{{ $product ? 'Edit Product' : 'New Product' }}</h1>
        </div>
    </section>

    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-3xl mx-auto px-6 lg:px-8">
            <form wire:submit="save" class="bg-white rounded-2xl border border-navy-100 p-6 space-y-4">
                <div>
                    <x-input-label for="name" value="Product Name" />
                    <x-text-input wire:model="name" id="name" class="block mt-1 w-full" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="category_id" value="Category" />
                        <select wire:model="category_id" id="category_id" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm">
                            <option value="">Select a category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="unit" value="Unit" />
                        <select wire:model="unit" id="unit" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm">
                            @foreach (['bag', 'ton', 'piece', 'meter', 'liter', 'roll'] as $u)
                                <option value="{{ $u }}">{{ ucfirst($u) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('unit')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="brand_id" value="Brand / Manufacturer (optional)" />
                    <select wire:model="brand_id" id="brand_id" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm">
                        <option value="">No brand</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-navy-400">Don't see the brand you need? Let the Selbuildi team know and we'll add it.</p>
                    <x-input-error :messages="$errors->get('brand_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="description" value="Description (optional)" />
                    <textarea wire:model="description" id="description" rows="3" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="specification" value="Specification (optional)" />
                    <textarea wire:model="specification" id="specification" rows="2" placeholder="E.g. 12mm, Grade 60, 12m length" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm"></textarea>
                    <p class="mt-1 text-xs text-navy-400">Size, grade, material - whatever helps a buyer match this to what they need.</p>
                    <x-input-error :messages="$errors->get('specification')" class="mt-1" />
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="price" value="Price (XAF)" />
                        <x-text-input wire:model="price" id="price" type="number" min="1" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('price')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="compare_at_price" value="Compare-at Price (optional)" />
                        <x-text-input wire:model="compare_at_price" id="compare_at_price" type="number" min="1" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('compare_at_price')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="min_order_qty" value="Minimum Order Qty" />
                        <x-text-input wire:model="min_order_qty" id="min_order_qty" type="number" min="1" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('min_order_qty')" class="mt-1" />
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="weight_kg" value="Weight in kg (optional)" />
                        <x-text-input wire:model="weight_kg" id="weight_kg" type="number" step="0.01" min="0" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('weight_kg')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="quantity_available" value="Stock Quantity" />
                        <x-text-input wire:model="quantity_available" id="quantity_available" type="number" min="0" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('quantity_available')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="images" value="Product Photos (optional)" />
                    <input wire:model="images" id="images" type="file" accept="image/*" multiple class="mt-1 block w-full text-sm text-navy-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-navy-50 file:text-navy-700 file:text-sm file:font-semibold hover:file:bg-navy-100" />
                    <p class="mt-1 text-xs text-navy-400" wire:loading wire:target="images">Uploading&hellip;</p>
                    <x-input-error :messages="$errors->get('images')" class="mt-1" />
                    <x-input-error :messages="$errors->get('images.*')" class="mt-1" />
                    <p class="mt-1 text-xs text-navy-400">Select multiple files to upload several photos at once. New uploads add to the gallery below; they don't replace it.</p>

                    @if ($product?->images->isNotEmpty())
                        <div class="mt-3 grid grid-cols-4 sm:grid-cols-6 gap-2">
                            @foreach ($product->images as $existingImage)
                                <div wire:key="existing-image-{{ $existingImage->id }}" class="relative group aspect-square rounded-lg overflow-hidden border border-navy-100">
                                    <img src="{{ asset('storage/'.$existingImage->path) }}" alt="" class="w-full h-full object-cover">
                                    <button
                                        type="button"
                                        wire:click="removeImage({{ $existingImage->id }})"
                                        wire:confirm="Remove this photo?"
                                        class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-semibold transition-opacity"
                                    >
                                        Remove
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $product ? 'Save Changes' : 'Create Product' }}</span>
                        <span wire:loading wire:target="save">Saving&hellip;</span>
                    </x-primary-button>
                    <a href="{{ route('supplier.products.index') }}" wire:navigate class="text-sm text-navy-500 hover:text-navy-700 transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</div>
