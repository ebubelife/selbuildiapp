<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SiteSetting;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(): Product
    {
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Test Supplier',
            'slug' => 'test-supplier-'.uniqid(),
            'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Cement', 'slug' => 'cement-'.uniqid(), 'icon' => 'cement']);

        return Product::create([
            'supplier_profile_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Dangote Cement 50kg',
            'slug' => 'dangote-cement-50kg-'.uniqid(),
            'sku' => 'SB-'.uniqid(),
            'unit' => 'bag',
            'price' => 4500,
            'min_order_qty' => 1,
            'is_active' => true,
        ]);
    }

    public function test_customer_dashboard_has_a_view_cart_link(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('View Cart')
            ->assertSee(route('checkout.index'));
    }

    public function test_contractor_dashboard_also_has_a_view_cart_link(): void
    {
        $user = User::factory()->create(['role' => 'contractor', 'email_verified_at' => now()]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('View Cart');
    }

    public function test_the_logo_links_home_not_back_to_the_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('home').'"', escape: false);
    }

    public function test_shop_materials_section_shows_a_products_own_photo_when_one_exists(): void
    {
        $product = $this->createProduct();
        ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/dashboard-test.jpg']);

        $user = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('storage/product-images/dashboard-test.jpg', escape: false);
    }

    public function test_shop_materials_section_falls_back_to_the_category_icon_without_a_photo(): void
    {
        $this->createProduct();

        $user = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_customer_dashboard_has_a_contact_link(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('support.create').'"', escape: false);
    }

    public function test_verified_supplier_dashboard_has_a_contact_link(): void
    {
        $user = User::factory()->create(['role' => 'supplier', 'email_verified_at' => now()]);
        SupplierProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Test Supplier',
            'slug' => 'test-supplier-'.uniqid(),
            'verified_at' => now(),
        ]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Contact Selbuildi')
            ->assertSee('href="'.route('support.create').'"', escape: false);
    }

    public function test_unverified_supplier_dashboard_still_has_a_contact_link(): void
    {
        $user = User::factory()->create(['role' => 'supplier', 'email_verified_at' => now()]);
        SupplierProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Pending Supplier',
            'slug' => 'pending-supplier-'.uniqid(),
        ]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Contact Selbuildi');
    }

    public function test_the_whatsapp_button_appears_on_the_dashboard_when_configured(): void
    {
        SiteSetting::set('whatsapp_number', '237670000000');

        $user = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('https://wa.me/237670000000', escape: false);
    }

    public function test_the_whatsapp_button_appears_on_the_supplier_dashboard_too(): void
    {
        SiteSetting::set('whatsapp_number', '237670000000');

        $user = User::factory()->create(['role' => 'supplier', 'email_verified_at' => now()]);
        SupplierProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Test Supplier',
            'slug' => 'test-supplier-'.uniqid(),
            'verified_at' => now(),
        ]);
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('https://wa.me/237670000000', escape: false);
    }
}
