<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(): Product
    {
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Test Supplier Co',
            'slug' => 'test-supplier-co',
            'verified_at' => now(),
        ]);

        $category = Category::create([
            'name' => 'Cement',
            'slug' => 'cement',
            'icon' => 'cement',
        ]);

        return Product::create([
            'supplier_profile_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Test Cement 50kg',
            'slug' => 'test-cement-50kg',
            'sku' => 'TEST-CEMENT-50KG',
            'unit' => 'bag',
            'price' => 4500,
            'min_order_qty' => 1,
            'is_active' => true,
            'is_featured' => true,
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
}
