<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): SupplierProfile
    {
        return SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Test Supplier',
            'slug' => 'test-supplier-'.uniqid(),
            'verified_at' => now(),
        ]);
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Cement', 'slug' => 'cement-'.uniqid(), 'icon' => 'cement']);
    }

    public function test_an_admin_can_create_a_product_with_photos_and_specification(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $category = $this->category();
        $brand = Brand::create(['name' => 'Dangote', 'slug' => 'dangote']);

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'supplier_profile_id' => $supplier->id,
                'name' => 'Dangote Cement 50kg',
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'unit' => 'bag',
                'price' => 4500,
                'min_order_qty' => 1,
                'specification' => '12mm, Grade 60, 12m length',
                'images' => ['product-images/a.jpg', 'product-images/b.jpg'],
                'quantity_available' => 250,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'Dangote Cement 50kg')->sole();

        $this->assertSame('12mm, Grade 60, 12m length', $product->specification);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertCount(2, $product->images);
        $this->assertSame(['product-images/a.jpg', 'product-images/b.jpg'], $product->images->pluck('path')->all());
        $this->assertSame(250, $product->inventories->sum('quantity_available'));
        $this->assertTrue($product->isInStock());
    }

    public function test_a_product_created_without_a_quantity_column_still_creates_an_inventory_row_defaulting_to_zero(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $category = $this->category();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'supplier_profile_id' => $supplier->id,
                'name' => 'Zero Stock Product',
                'category_id' => $category->id,
                'unit' => 'bag',
                'price' => 4500,
                'min_order_qty' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'Zero Stock Product')->sole();

        $this->assertSame(1, $product->inventories()->count());
        $this->assertFalse($product->isInStock());
    }

    public function test_an_admin_can_update_stock_quantity_when_editing_a_product(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = $this->supplier();
        $product = Product::create([
            'supplier_profile_id' => $supplier->id,
            'category_id' => $this->category()->id,
            'name' => 'Roofing Sheet',
            'slug' => 'roofing-sheet-'.uniqid(),
            'sku' => 'SB-'.uniqid(),
            'unit' => 'piece',
            'price' => 9500,
            'min_order_qty' => 1,
            'is_active' => true,
        ]);
        $warehouse = Warehouse::create(['supplier_profile_id' => $supplier->id, 'name' => 'Main Warehouse']);
        Inventory::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity_available' => 5]);

        $this->actingAs($admin, 'admin');

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(['quantity_available' => 5])
            ->fillForm(['quantity_available' => 40])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(40, $product->inventories()->sum('quantity_available'));
    }

    public function test_an_admin_can_add_a_photo_to_an_existing_product_without_disturbing_existing_ones(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('product-images/new.jpg', 'fake-content');

        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'supplier_profile_id' => $this->supplier()->id,
            'category_id' => $this->category()->id,
            'name' => 'Roofing Sheet',
            'slug' => 'roofing-sheet-'.uniqid(),
            'sku' => 'SB-'.uniqid(),
            'unit' => 'piece',
            'price' => 9500,
            'min_order_qty' => 1,
            'is_active' => true,
        ]);
        ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/old.jpg', 'sort_order' => 0]);

        $this->actingAs($admin, 'admin');

        // The "Add Photos" field starts empty on edit (existing photos are
        // managed separately, see removeExistingImage below) - filling it
        // just appends, it never needs to already contain old.jpg.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(['images' => []])
            ->fillForm(['images' => ['product-images/new.jpg']])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertCount(2, $product->images);
        $this->assertSame(['product-images/old.jpg', 'product-images/new.jpg'], $product->images->pluck('path')->all());
    }

    public function test_an_admin_can_remove_an_existing_photo_immediately(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('product-images/old.jpg', 'fake-content');
        Storage::disk('public')->put('product-images/keep.jpg', 'fake-content');

        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'supplier_profile_id' => $this->supplier()->id,
            'category_id' => $this->category()->id,
            'name' => 'Roofing Sheet',
            'slug' => 'roofing-sheet-'.uniqid(),
            'sku' => 'SB-'.uniqid(),
            'unit' => 'piece',
            'price' => 9500,
            'min_order_qty' => 1,
            'is_active' => true,
        ]);
        $old = ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/old.jpg', 'sort_order' => 0]);
        ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/keep.jpg', 'sort_order' => 1]);

        $this->actingAs($admin, 'admin');

        // Removal is a direct method call (a click on the gallery's Remove
        // button), not part of the form's Save flow - it takes effect
        // right away, with no separate save step.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->call('removeExistingImage', $old->id);

        $product->refresh();
        $this->assertCount(1, $product->images);
        $this->assertSame('product-images/keep.jpg', $product->images->first()->path);
        Storage::disk('public')->assertMissing('product-images/old.jpg');
    }

    public function test_an_admin_cannot_remove_another_products_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('product-images/other.jpg', 'fake-content');

        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'supplier_profile_id' => $this->supplier()->id,
            'category_id' => $this->category()->id,
            'name' => 'Roofing Sheet',
            'slug' => 'roofing-sheet-'.uniqid(),
            'sku' => 'SB-'.uniqid(),
            'unit' => 'piece',
            'price' => 9500,
            'min_order_qty' => 1,
            'is_active' => true,
        ]);
        $otherProduct = Product::create([
            'supplier_profile_id' => $this->supplier()->id,
            'category_id' => $this->category()->id,
            'name' => 'Other Product',
            'slug' => 'other-product-'.uniqid(),
            'sku' => 'SB-'.uniqid(),
            'unit' => 'piece',
            'price' => 1000,
            'min_order_qty' => 1,
            'is_active' => true,
        ]);
        $otherImage = ProductImage::create(['product_id' => $otherProduct->id, 'path' => 'product-images/other.jpg', 'sort_order' => 0]);

        $this->actingAs($admin, 'admin');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->call('removeExistingImage', $otherImage->id);

        $this->assertDatabaseHas('product_images', ['id' => $otherImage->id]);
    }
}
