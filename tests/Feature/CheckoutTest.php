<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(int $price = 9500): Product
    {
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Douala Building Depot',
            'slug' => 'douala-building-depot-'.uniqid(),
            'verified_at' => now(),
        ]);

        $category = Category::create([
            'name' => 'Roofing',
            'slug' => 'roofing-'.uniqid(),
            'icon' => 'roofing',
        ]);

        return Product::create([
            'supplier_profile_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Aluminium Roofing Sheet',
            'slug' => 'aluminium-roofing-sheet-'.uniqid(),
            'sku' => 'ROOF-'.uniqid(),
            'unit' => 'piece',
            'price' => $price,
            'min_order_qty' => 1,
            'is_active' => true,
            'is_featured' => false,
        ]);
    }

    private function customerWithCartItem(int $quantity = 3): array
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct();

        $this->actingAs($user);
        app(CartService::class)->add($product, $quantity);

        return [$user, $product];
    }

    public function test_guest_is_redirected_away_from_checkout(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('login'));
    }

    public function test_checkout_redirects_to_shop_when_cart_is_empty(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        Volt::test('checkout.index')
            ->assertRedirect(route('shop.index'));
    }

    public function test_checkout_preselects_users_default_address(): void
    {
        [$user] = $this->customerWithCartItem();

        $address = $user->addresses()->create([
            'recipient_name' => 'Test Customer',
            'phone' => '+237600000000',
            'country' => 'Cameroon',
            'city' => 'Douala',
            'street' => '123 Rue de la Paix',
            'is_default' => true,
        ]);

        Volt::test('checkout.index')
            ->assertSet('selectedAddressId', $address->id)
            ->assertSet('showNewAddressForm', false);
    }

    public function test_checkout_shows_new_address_form_when_user_has_no_address(): void
    {
        $this->customerWithCartItem();

        Volt::test('checkout.index')
            ->assertSet('selectedAddressId', null)
            ->assertSet('showNewAddressForm', true);
    }

    public function test_saving_a_new_address_selects_it_and_resets_the_form(): void
    {
        $this->customerWithCartItem();

        Volt::test('checkout.index')
            ->set('recipient_name', 'Test Customer')
            ->set('phone', '+237600000000')
            ->set('country', 'Cameroon')
            ->set('region', 'Littoral')
            ->set('city', 'Douala')
            ->set('street', '123 Rue de la Paix')
            ->call('saveNewAddress')
            ->assertSet('showNewAddressForm', false)
            ->assertSet('recipient_name', '');

        $this->assertDatabaseHas('addresses', [
            'recipient_name' => 'Test Customer',
            'city' => 'Douala',
        ]);
    }

    public function test_a_country_not_enabled_for_checkout_is_rejected(): void
    {
        $this->customerWithCartItem();

        Volt::test('checkout.index')
            ->set('recipient_name', 'Test Customer')
            ->set('phone', '+237600000000')
            ->set('country', 'Nigeria') // not checkout-enabled (only Cameroon is, by default)
            ->set('city', 'Lagos')
            ->set('street', '1 Main Street')
            ->call('saveNewAddress')
            ->assertHasErrors('country');
    }

    public function test_the_delivery_address_step_clarifies_where_the_order_ships_to(): void
    {
        $this->customerWithCartItem();

        Volt::test('checkout.index')
            ->set('step', 'address')
            ->assertSee('This is where your order will be delivered');
    }

    public function test_place_order_requires_a_selected_address(): void
    {
        $this->customerWithCartItem();

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->call('placeOrder')
            ->assertHasErrors(['selectedAddressId']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_placing_an_order_creates_order_items_status_history_and_clears_the_cart(): void
    {
        [$user, $product] = $this->customerWithCartItem(3);

        $address = $user->addresses()->create([
            'recipient_name' => 'Test Customer',
            'phone' => '+237600000000',
            'country' => 'Cameroon',
            'city' => 'Douala',
            'street' => '123 Rue de la Paix',
            'is_default' => true,
        ]);

        $component = Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->set('selectedAddressId', $address->id)
            ->call('placeOrder');

        $this->assertDatabaseCount('orders', 1);

        $order = \App\Models\Order::sole();

        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('pending', $order->status);
        $this->assertSame(3 * $product->price, $order->total);
        $this->assertSame($address->id, $order->shipping_address_id);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status' => 'pending',
            'changed_by' => $user->id,
        ]);

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'supplier_profile_id' => $product->supplier_profile_id,
            'status' => 'pending',
        ]);

        $this->assertSame(0, $user->fresh()->cart->items()->count());

        $component->assertRedirect(route('orders.show', $order));
    }

    public function test_placing_an_order_decrements_inventory_when_stock_record_exists(): void
    {
        [$user, $product] = $this->customerWithCartItem(3);

        $warehouse = \App\Models\Warehouse::create([
            'supplier_profile_id' => $product->supplier_profile_id,
            'name' => 'Main Warehouse',
        ]);

        \App\Models\Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        $address = $user->addresses()->create([
            'recipient_name' => 'Test Customer',
            'phone' => '+237600000000',
            'country' => 'Cameroon',
            'city' => 'Douala',
            'street' => '123 Rue de la Paix',
            'is_default' => true,
        ]);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->set('selectedAddressId', $address->id)
            ->call('placeOrder');

        $this->assertSame(7, \App\Models\Inventory::where('product_id', $product->id)->value('quantity_available'));
    }

    public function test_a_user_cannot_view_another_users_order(): void
    {
        [$owner] = $this->customerWithCartItem();
        $address = $owner->addresses()->create([
            'recipient_name' => 'Test Customer',
            'phone' => '+237600000000',
            'country' => 'Cameroon',
            'city' => 'Douala',
            'street' => '123 Rue de la Paix',
            'is_default' => true,
        ]);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->set('selectedAddressId', $address->id)
            ->call('placeOrder');

        $order = \App\Models\Order::sole();

        $intruder = User::factory()->create(['role' => 'customer']);
        $this->actingAs($intruder);

        Volt::test('orders.show', ['order' => $order])
            ->assertStatus(403);
    }

    public function test_choosing_an_enabled_online_gateway_creates_a_pending_payment_and_redirects_to_hosted_checkout(): void
    {
        // Flutterwave, not Paystack - Paystack doesn't support XAF at all
        // (see PaymentGatewayManager::SUPPORTED_CURRENCIES), so it's
        // correctly filtered out of the payment-method options for a
        // customer paying in XAF, same as any other currency mismatch.
        PaymentGateway::create([
            'provider' => 'flutterwave',
            'display_name' => 'Flutterwave',
            'is_enabled' => true,
            'mode' => 'test',
            'credentials' => ['secret_key' => 'sk_test_abc'],
        ]);

        [$user, $product] = $this->customerWithCartItem(2);
        $address = $user->addresses()->create([
            'recipient_name' => 'Test Customer',
            'phone' => '+237600000000',
            'country' => 'Cameroon',
            'city' => 'Douala',
            'street' => '123 Rue de la Paix',
            'is_default' => true,
        ]);

        Http::fake(['api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/xyz'],
        ])]);

        $component = Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->set('selectedAddressId', $address->id)
            ->set('paymentMethod', 'flutterwave')
            ->call('placeOrder');

        $order = \App\Models\Order::sole();
        $this->assertSame('flutterwave', $order->payment_method);

        $payment = \App\Models\Payment::sole();
        $this->assertSame('flutterwave', $payment->provider);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(2 * $product->price, $payment->amount);

        $component->assertRedirect('https://checkout.flutterwave.com/pay/xyz');
    }

    public function test_a_disabled_gateway_cannot_be_used_even_if_submitted_directly(): void
    {
        [$user] = $this->customerWithCartItem();
        $address = $user->addresses()->create([
            'recipient_name' => 'Test Customer',
            'phone' => '+237600000000',
            'country' => 'Cameroon',
            'city' => 'Douala',
            'street' => '123 Rue de la Paix',
            'is_default' => true,
        ]);

        // No PaymentGateway rows seeded/enabled - simulates a stale UI
        // state or a directly-manipulated Livewire payload.
        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->set('selectedAddressId', $address->id)
            ->set('paymentMethod', 'paystack')
            ->call('placeOrder');

        $order = \App\Models\Order::sole();
        $this->assertSame('cash_on_delivery', $order->payment_method);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_failed_payment_initialize_still_leaves_the_order_placed_and_redirects_gracefully(): void
    {
        PaymentGateway::create([
            'provider' => 'flutterwave',
            'display_name' => 'Flutterwave',
            'is_enabled' => true,
            'mode' => 'test',
            'credentials' => ['secret_key' => 'sk_test_abc'],
        ]);

        [$user] = $this->customerWithCartItem();
        $address = $user->addresses()->create([
            'recipient_name' => 'Test Customer',
            'phone' => '+237600000000',
            'country' => 'Cameroon',
            'city' => 'Douala',
            'street' => '123 Rue de la Paix',
            'is_default' => true,
        ]);

        // Simulate Flutterwave's API being down/erroring.
        Http::fake(['api.flutterwave.com/*' => Http::response(['status' => 'error'], 500)]);

        $component = Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->set('selectedAddressId', $address->id)
            ->set('paymentMethod', 'flutterwave')
            ->call('placeOrder');

        // The order and payment already saved successfully before the
        // provider call - a failed redirect must not undo that.
        $order = \App\Models\Order::sole();
        $this->assertSame('pending', $order->payment_status);

        $payment = \App\Models\Payment::sole();
        $this->assertSame('pending', $payment->status);

        $component->assertRedirect(route('orders.show', $order));
        $this->assertNotNull(session('paymentError'));
    }

    public function test_mobile_money_is_shown_separately_from_card_gateways(): void
    {
        PaymentGateway::create(['provider' => 'fapshi', 'display_name' => 'Fapshi (MTN/Orange Money)', 'is_enabled' => true, 'mode' => 'test', 'credentials' => []]);
        PaymentGateway::create(['provider' => 'flutterwave', 'display_name' => 'Flutterwave', 'is_enabled' => true, 'mode' => 'test', 'credentials' => []]);

        $this->customerWithCartItem();

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->assertSee('Or pay with Mobile Money')
            ->assertSee('MTN Mobile Money / Orange Money')
            ->assertSee('Pay with Flutterwave');
    }
}
