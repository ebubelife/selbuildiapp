<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CreditTierSetting;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_without_a_photo_falls_back_to_its_icon(): void
    {
        Category::create(['name' => 'Cement', 'slug' => 'cement', 'icon' => 'cement']);

        $this->get(route('home'))->assertOk();
    }

    public function test_a_category_with_a_photo_renders_it(): void
    {
        Category::create([
            'name' => 'Cement',
            'slug' => 'cement',
            'icon' => 'cement',
            'image' => 'category-images/cement.jpg',
        ]);

        $this->get(route('home'))
            ->assertSee('storage/category-images/cement.jpg', escape: false);
    }

    public function test_featured_products_show_supplier_and_stock_status(): void
    {
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Douala Building Depot',
            'slug' => 'douala-building-depot',
            'verified_at' => now(),
        ]);

        $category = Category::create(['name' => 'Cement', 'slug' => 'cement', 'icon' => 'cement']);

        $product = Product::create([
            'supplier_profile_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Dangote Cement 50kg',
            'slug' => 'dangote-cement-50kg',
            'sku' => 'DANGOTE-50KG',
            'unit' => 'bag',
            'price' => 4800,
            'min_order_qty' => 1,
            'is_active' => true,
            'is_featured' => true,
        ]);

        $warehouse = Warehouse::create(['supplier_profile_id' => $supplier->id, 'name' => 'Main Warehouse']);
        Inventory::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity_available' => 20]);
        ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/cement.jpg']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Douala Building Depot')
            ->assertSee('In Stock')
            ->assertSee('storage/product-images/cement.jpg', escape: false);
    }

    public function test_the_trust_and_credit_section_reflects_admin_edited_tier_terms(): void
    {
        CreditTierSetting::where('tier', 'gold')->update(['perk_headline' => 'Net-20 credit terms']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Net-20 credit terms')
            ->assertDontSee('Net-15 credit terms');
    }
}
