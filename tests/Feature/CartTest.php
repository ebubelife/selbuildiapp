<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(int $price = 4500): Product
    {
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Test Supplier Co',
            'slug' => 'test-supplier-co-'.uniqid(),
            'verified_at' => now(),
        ]);

        $category = Category::create([
            'name' => 'Cement',
            'slug' => 'cement-'.uniqid(),
            'icon' => 'cement',
        ]);

        return Product::create([
            'supplier_profile_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Test Cement 50kg',
            'slug' => 'test-cement-50kg-'.uniqid(),
            'sku' => 'TEST-CEMENT-'.uniqid(),
            'unit' => 'bag',
            'price' => $price,
            'min_order_qty' => 1,
            'is_active' => true,
            'is_featured' => false,
        ]);
    }

    public function test_guest_cart_is_created_against_session_id(): void
    {
        Session::start();
        $product = $this->createProduct();

        app(CartService::class)->add($product, 2);

        $cart = app(CartService::class)->current();

        $this->assertNull($cart->user_id);
        $this->assertSame(Session::getId(), $cart->session_id);
        $this->assertSame(2, $cart->totalQuantity());
    }

    public function test_adding_same_product_twice_increments_quantity_instead_of_duplicating(): void
    {
        Session::start();
        $product = $this->createProduct();

        $service = app(CartService::class);
        $service->add($product, 1);
        $service->add($product, 2);

        $cart = $service->current();

        $this->assertSame(1, $cart->items()->count());
        $this->assertSame(3, $cart->totalQuantity());
    }

    public function test_authenticated_user_cart_is_keyed_by_user_not_session(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        $product = $this->createProduct();
        app(CartService::class)->add($product, 1);

        $cart = app(CartService::class)->current();

        $this->assertSame($user->id, $cart->user_id);
        $this->assertNull($cart->session_id);
    }

    public function test_cart_subtotal_sums_line_totals(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        $productA = $this->createProduct(4500);
        $productB = $this->createProduct(1000);

        $service = app(CartService::class);
        $service->add($productA, 2); // 9000
        $service->add($productB, 3); // 3000

        $this->assertSame(12000, $service->current()->subtotal());
    }

    public function test_merging_guest_cart_into_user_cart_combines_matching_items(): void
    {
        $product = $this->createProduct();
        $user = User::factory()->create(['role' => 'customer']);

        Session::start();
        $guestSessionId = Session::getId();
        app(CartService::class)->add($product, 2);

        app(CartService::class)->mergeSessionCartInto($user, $guestSessionId);

        $userCart = $user->fresh()->cart;

        $this->assertNotNull($userCart);
        $this->assertSame(2, $userCart->totalQuantity());
        $this->assertDatabaseMissing('carts', ['session_id' => $guestSessionId]);
    }

    public function test_merging_guest_cart_adds_to_existing_quantity_for_same_product(): void
    {
        $product = $this->createProduct();
        $user = User::factory()->create(['role' => 'customer']);

        // User already has 1 of the product in their own cart.
        $this->actingAs($user);
        app(CartService::class)->add($product, 1);

        // A guest session (e.g. a second browser tab) added 2 more before login.
        Session::start();
        $guestSessionId = Session::getId();
        app(CartService::class)->add($product, 2);

        app(CartService::class)->mergeSessionCartInto($user, $guestSessionId);

        $this->assertSame(3, $user->fresh()->cart->totalQuantity());
    }

    public function test_merging_an_empty_or_missing_guest_cart_is_a_no_op(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        app(CartService::class)->mergeSessionCartInto($user, 'nonexistent-session-id');

        $this->assertNull($user->fresh()->cart);
    }
}
