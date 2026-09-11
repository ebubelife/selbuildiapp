<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Services\CartService;
use App\Services\CurrencyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CheckoutCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(int $price = 4500): Product
    {
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Douala Building Depot',
            'slug' => 'douala-building-depot-'.uniqid(),
            'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Roofing', 'slug' => 'roofing-'.uniqid(), 'icon' => 'roofing']);

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
        ]);
    }

    private function customerPayingIn(string $currencyCode, int $quantity = 1): array
    {
        $user = User::factory()->create(['role' => 'customer', 'preferred_currency' => $currencyCode]);
        $product = $this->createProduct();

        $this->actingAs($user);
        app(CartService::class)->add($product, $quantity);
        $user->addresses()->create([
            'recipient_name' => 'Test Customer',
            'phone' => '+237600000000',
            'country' => 'Cameroon',
            'city' => 'Douala',
            'street' => '123 Rue de la Paix',
            'is_default' => true,
        ]);

        return [$user, $product];
    }

    public function test_placing_an_order_in_a_non_xaf_currency_charges_the_converted_amount(): void
    {
        PaymentGateway::create(['provider' => 'flutterwave', 'display_name' => 'Flutterwave', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test']]);
        Currency::where('code', 'NGN')->update(['rate_to_xaf' => 0.5]); // 1 NGN = 0.5 XAF

        [$user, $product] = $this->customerPayingIn('NGN');

        Http::fake(['api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/xyz'],
        ])]);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->set('selectedAddressId', $user->addresses()->sole()->id)
            ->set('paymentMethod', 'flutterwave')
            ->call('placeOrder');

        $order = Order::sole();
        $this->assertSame('NGN', $order->currency);
        $this->assertSame('0.500000', (string) $order->exchange_rate);
        // subtotal/total stay in XAF - the canonical ledger amount.
        $this->assertSame(4500, $order->total);

        $payment = Payment::sole();
        $this->assertSame('NGN', $payment->currency);
        // 4500 XAF / 0.5 = 9000 NGN.
        $this->assertSame(9000, $payment->amount);
    }

    public function test_paystack_becomes_usable_once_paying_in_a_currency_it_supports(): void
    {
        PaymentGateway::create(['provider' => 'paystack', 'display_name' => 'Paystack', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test']]);

        [$user] = $this->customerPayingIn('NGN');

        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz'],
        ])]);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->assertSee('Pay with Paystack')
            ->set('selectedAddressId', $user->addresses()->sole()->id)
            ->set('paymentMethod', 'paystack')
            ->call('placeOrder')
            ->assertRedirect('https://checkout.paystack.com/xyz');

        $this->assertSame('paystack', Payment::sole()->provider);
    }

    public function test_fapshi_is_hidden_once_paying_in_a_currency_it_does_not_support(): void
    {
        PaymentGateway::create(['provider' => 'fapshi', 'display_name' => 'Fapshi', 'is_enabled' => true, 'mode' => 'test', 'credentials' => []]);
        PaymentGateway::create(['provider' => 'flutterwave', 'display_name' => 'Flutterwave', 'is_enabled' => true, 'mode' => 'test', 'credentials' => []]);

        [$user] = $this->customerPayingIn('NGN');

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->assertDontSee('MTN Mobile Money');
    }

    public function test_cash_on_delivery_is_unavailable_once_paying_in_a_non_xaf_currency(): void
    {
        [$user] = $this->customerPayingIn('NGN');

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->assertDontSee('Cash / Pay on Delivery');
    }

    public function test_selbuildi_credit_is_unavailable_once_paying_in_a_non_xaf_currency(): void
    {
        [$user] = $this->customerPayingIn('NGN');
        $user->creditAccount()->create([
            'status' => 'approved',
            'credit_limit' => 1000000,
            'available_credit' => 1000000,
            'approved_at' => now(),
        ]);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->assertDontSee('Pay with Selbuildi Credit');
    }

    public function test_placing_an_order_in_a_non_xaf_currency_with_no_valid_payment_method_shows_an_error(): void
    {
        // No gateway at all enabled - simulates a stale UI still showing
        // "cash_on_delivery" selected from before the currency changed.
        [$user] = $this->customerPayingIn('NGN');

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->set('selectedAddressId', $user->addresses()->sole()->id)
            ->set('paymentMethod', 'cash_on_delivery')
            ->call('placeOrder')
            ->assertHasErrors('paymentMethod');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_retrying_payment_uses_the_orders_original_locked_in_rate_not_the_current_one(): void
    {
        PaymentGateway::create(['provider' => 'flutterwave', 'display_name' => 'Flutterwave', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test']]);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'status' => 'pending',
            'subtotal' => 9500,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 9500,
            'currency' => 'NGN',
            'exchange_rate' => 0.5, // locked in at placement: 1 NGN = 0.5 XAF
            'payment_status' => 'failed',
            'payment_method' => 'flutterwave',
            'placed_at' => now(),
        ]);

        // Admin changes the live rate afterward - the retry must not use this.
        Currency::where('code', 'NGN')->update(['rate_to_xaf' => 0.9]);

        Http::fake(['api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/xyz'],
        ])]);

        $this->actingAs($order->user);

        \Livewire\Livewire::test('orders.show', ['order' => $order])->call('retryPayment');

        // 9500 / 0.5 = 19000, NOT 9500 / 0.9.
        $this->assertSame(19000, Payment::sole()->amount);
    }
}
