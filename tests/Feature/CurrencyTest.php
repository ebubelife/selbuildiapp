<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Services\CartService;
use App\Services\CurrencyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(int $price = 4500): Product
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
            'price' => $price,
            'min_order_qty' => 1,
            'is_active' => true,
        ]);
    }

    // --- Currency model ---

    public function test_xaf_conversion_is_a_no_op(): void
    {
        $xaf = Currency::where('code', 'XAF')->sole();

        $this->assertSame(4500, $xaf->convertFromXaf(4500));
        $this->assertSame('4,500 XAF', $xaf->format(4500));
    }

    public function test_converts_and_rounds_to_a_whole_unit(): void
    {
        $usd = Currency::where('code', 'USD')->sole();
        $usd->update(['rate_to_xaf' => 600]);

        // 4500 / 600 = 7.5, rounds to 8.
        $this->assertSame(8, $usd->convertFromXaf(4500));
        $this->assertSame('$8', $usd->format(4500));
    }

    public function test_supported_options_excludes_disabled_currencies(): void
    {
        Currency::where('code', 'NGN')->update(['is_supported' => false]);

        $options = Currency::supportedOptions();

        $this->assertArrayHasKey('USD', $options);
        $this->assertArrayNotHasKey('NGN', $options);
    }

    // --- CurrencyContext resolution ---

    public function test_defaults_to_xaf_for_a_guest(): void
    {
        $this->assertSame('XAF', app(CurrencyContext::class)->current()->code);
    }

    public function test_uses_a_logged_in_users_saved_preference(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'preferred_currency' => 'NGN']);
        $this->actingAs($user);

        $this->assertSame('NGN', app(CurrencyContext::class)->current()->code);
    }

    public function test_falls_back_to_the_users_country_currency_when_no_preference_is_set(): void
    {
        Country::create(['name' => 'Nigeria', 'code' => 'NG', 'currency_code' => 'NGN']);
        $user = User::factory()->create(['role' => 'customer', 'country' => 'Nigeria', 'preferred_currency' => '']);
        $this->actingAs($user);

        $this->assertSame('NGN', app(CurrencyContext::class)->current()->code);
    }

    public function test_switching_currency_persists_for_the_session_and_the_account(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        app(CurrencyContext::class)->switchTo('USD', $user);

        $this->assertSame('USD', session('currency_code'));
        $this->assertSame('USD', $user->fresh()->preferred_currency);
    }

    public function test_cannot_switch_to_an_unsupported_currency(): void
    {
        Currency::where('code', 'USD')->update(['is_supported' => false]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        app(CurrencyContext::class)->switchTo('USD');
    }

    // --- End-to-end: the converted price actually renders on real pages ---

    public function test_shop_index_renders_the_converted_price_once_ngn_is_active(): void
    {
        Currency::where('code', 'NGN')->update(['rate_to_xaf' => 0.5]);
        $product = $this->createProduct(4500);

        $user = User::factory()->create(['role' => 'customer', 'preferred_currency' => 'NGN']);
        $this->actingAs($user);

        // 4500 / 0.5 = 9000.
        Volt::test('shop.index')->assertSee('₦9,000', escape: false);
    }

    public function test_product_detail_page_renders_the_converted_price_once_ngn_is_active(): void
    {
        Currency::where('code', 'NGN')->update(['rate_to_xaf' => 0.5]);
        $product = $this->createProduct(4500);

        $user = User::factory()->create(['role' => 'customer', 'preferred_currency' => 'NGN']);
        $this->actingAs($user);

        $this->get(route('shop.show', $product))
            ->assertOk()
            ->assertSee('₦9,000', escape: false)
            ->assertSee('4,500 XAF', escape: false); // the XAF hint, for cross-checking
    }

    public function test_cart_widget_renders_the_converted_price_once_ngn_is_active(): void
    {
        Currency::where('code', 'NGN')->update(['rate_to_xaf' => 0.5]);
        $product = $this->createProduct(4500);

        $user = User::factory()->create(['role' => 'customer', 'preferred_currency' => 'NGN']);
        $this->actingAs($user);
        app(CartService::class)->add($product, 1);

        Volt::test('cart-widget')->assertSee('₦9,000', escape: false);
    }

    public function test_xaf_stays_unchanged_for_a_customer_who_has_not_switched(): void
    {
        $product = $this->createProduct(4500);

        Volt::test('shop.index')->assertSee('4,500 XAF', escape: false);
    }
}
