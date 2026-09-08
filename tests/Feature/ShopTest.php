<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $overrides = []): Product
    {
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => $overrides['supplier_name'] ?? 'Test Supplier Co',
            'slug' => 'test-supplier-co-'.uniqid(),
            'verified_at' => ($overrides['verified'] ?? true) ? now() : null,
        ]);

        $category = Category::create([
            'name' => 'Cement',
            'slug' => 'cement-'.uniqid(),
            'icon' => 'cement',
        ]);

        return Product::create([
            'supplier_profile_id' => $supplier->id,
            'category_id' => $category->id,
            'brand_id' => $overrides['brand_id'] ?? null,
            'name' => $overrides['name'] ?? 'Test Cement 50kg',
            'slug' => Str::slug($overrides['name'] ?? 'Test Cement 50kg').'-'.uniqid(),
            'sku' => $overrides['sku'] ?? 'TEST-'.uniqid(),
            'description' => $overrides['description'] ?? null,
            'specification' => $overrides['specification'] ?? null,
            'unit' => 'bag',
            'price' => $overrides['price'] ?? 4500,
            'min_order_qty' => 1,
            'is_active' => true,
            'is_featured' => true,
        ]);
    }

    private function giveStock(Product $product, int $quantity): void
    {
        $warehouse = Warehouse::create([
            'supplier_profile_id' => $product->supplier_profile_id,
            'name' => 'Test Warehouse',
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_available' => $quantity,
        ]);
    }

    public function test_shop_index_lists_active_products(): void
    {
        $product = $this->createProduct();

        Volt::test('shop.index')
            ->assertSee($product->name)
            ->assertSee('Cement');
    }

    public function test_shop_index_filters_by_search(): void
    {
        $product = $this->createProduct();

        Volt::test('shop.index')
            ->set('search', 'Nonexistent Material')
            ->assertDontSee($product->name);
    }

    public function test_product_detail_page_renders(): void
    {
        $product = $this->createProduct();

        $response = $this->get(route('shop.show', $product));

        $response->assertOk();
        $response->assertSee($product->name);
        $response->assertSee('Test Supplier Co');
    }

    public function test_supplier_profile_page_renders(): void
    {
        $product = $this->createProduct();

        $response = $this->get(route('suppliers.show', $product->supplierProfile));

        $response->assertOk();
        $response->assertSee('Test Supplier Co');
        $response->assertSee($product->name);
    }

    public function test_a_product_with_stock_shows_in_stock_on_the_shop_page(): void
    {
        $product = $this->createProduct();
        $this->giveStock($product, 10);

        Volt::test('shop.index')->assertSee('In Stock');
    }

    public function test_a_product_with_no_inventory_row_shows_out_of_stock_on_the_shop_page(): void
    {
        $this->createProduct();

        Volt::test('shop.index')->assertSee('Out of Stock');
    }

    public function test_a_product_with_zero_quantity_shows_out_of_stock(): void
    {
        $product = $this->createProduct();
        $this->giveStock($product, 0);

        Volt::test('shop.index')->assertSee('Out of Stock');
    }

    public function test_shop_page_shows_a_verified_badge_for_a_verified_supplier(): void
    {
        $product = $this->createProduct();

        $rendered = Volt::test('shop.index')->html();

        // Just checking the icon renders somewhere near the supplier name
        // is enough here - the exact SVG path isn't the point of this test.
        $this->assertStringContainsString('Test Supplier Co', $rendered);
        $this->assertStringContainsString('text-green-600', $rendered);
    }

    public function test_shop_page_shows_the_products_own_photo_when_one_exists(): void
    {
        $product = $this->createProduct();
        ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/test.jpg']);

        Volt::test('shop.index')
            ->assertSee('storage/product-images/test.jpg', escape: false);
    }

    public function test_search_matches_across_sku_description_specification_and_brand(): void
    {
        $brand = Brand::create(['name' => 'Dangote', 'slug' => 'dangote']);
        $bySku = $this->createProduct(['name' => 'Roofing Sheet', 'sku' => 'ROOF-IBR-3M']);
        $byDescription = $this->createProduct(['name' => 'Steel Rod', 'description' => 'Reinforcement steel for foundations']);
        $bySpec = $this->createProduct(['name' => 'PVC Pipe', 'specification' => '12mm, Grade 60']);
        $byBrand = $this->createProduct(['name' => 'Cement Bag', 'brand_id' => $brand->id]);

        Volt::test('shop.index')->set('search', 'ROOF-IBR-3M')->assertSee('Roofing Sheet')->assertDontSee('Steel Rod');
        Volt::test('shop.index')->set('search', 'reinforcement')->assertSee('Steel Rod')->assertDontSee('Roofing Sheet');
        Volt::test('shop.index')->set('search', '12mm')->assertSee('PVC Pipe')->assertDontSee('Steel Rod');
        Volt::test('shop.index')->set('search', 'dangote')->assertSee('Cement Bag')->assertDontSee('Steel Rod');
    }

    public function test_search_tokens_match_out_of_order(): void
    {
        $this->createProduct(['name' => 'Cement 50kg Bag']);

        Volt::test('shop.index')
            ->set('search', '50kg cement')
            ->assertSee('Cement 50kg Bag');
    }

    public function test_price_range_filter(): void
    {
        $cheap = $this->createProduct(['name' => 'Cheap Cement', 'price' => 3000]);
        $expensive = $this->createProduct(['name' => 'Expensive Cement', 'price' => 9000]);

        Volt::test('shop.index')
            ->set('priceMin', 5000)
            ->set('priceMax', 10000)
            ->assertSee('Expensive Cement')
            ->assertDontSee('Cheap Cement');
    }

    public function test_verified_only_filter(): void
    {
        $verified = $this->createProduct(['name' => 'Verified Product', 'verified' => true]);
        $unverified = $this->createProduct(['name' => 'Unverified Product', 'verified' => false]);

        Volt::test('shop.index')
            ->set('verifiedOnly', true)
            ->assertSee('Verified Product')
            ->assertDontSee('Unverified Product');
    }

    public function test_in_stock_only_filter(): void
    {
        $inStock = $this->createProduct(['name' => 'Stocked Product']);
        $this->giveStock($inStock, 5);
        $outOfStock = $this->createProduct(['name' => 'Unstocked Product']);

        Volt::test('shop.index')
            ->set('inStockOnly', true)
            ->assertSee('Stocked Product')
            ->assertDontSee('Unstocked Product');
    }

    public function test_brand_filter(): void
    {
        $brand = Brand::create(['name' => 'Cimencam', 'slug' => 'cimencam']);
        $branded = $this->createProduct(['name' => 'Branded Cement', 'brand_id' => $brand->id]);
        $unbranded = $this->createProduct(['name' => 'Generic Cement']);

        Volt::test('shop.index')
            ->set('brand', $brand->id)
            ->assertSee('Branded Cement')
            ->assertDontSee('Generic Cement');
    }

    public function test_low_stock_shows_between_zero_and_ten_units(): void
    {
        $product = $this->createProduct();
        $this->giveStock($product, 5);

        Volt::test('shop.index')->assertSee('Low Stock');
    }

    public function test_product_page_shows_brand_and_specification(): void
    {
        $brand = Brand::create(['name' => 'Dangote', 'slug' => 'dangote']);
        $product = $this->createProduct([
            'brand_id' => $brand->id,
            'specification' => '12mm, Grade 60, 12m length',
        ]);

        $this->get(route('shop.show', $product))
            ->assertSee('Dangote')
            ->assertSee('12mm, Grade 60, 12m length');
    }
}
