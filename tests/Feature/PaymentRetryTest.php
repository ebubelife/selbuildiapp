<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PaymentRetryTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $user, array $overrides = []): Order
    {
        return Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $user->id,
            'status' => 'pending',
            'subtotal' => 9500,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 9500,
            'currency' => 'XAF',
            'payment_status' => 'failed',
            'payment_method' => 'flutterwave',
            'placed_at' => now(),
            ...$overrides,
        ]);
    }

    // --- Order model helpers ---

    public function test_online_payment_methods_are_identified_correctly(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->assertTrue($this->makeOrder($user, ['payment_method' => 'flutterwave'])->isOnlinePayment());
        $this->assertTrue($this->makeOrder($user, ['payment_method' => 'paystack'])->isOnlinePayment());
        $this->assertTrue($this->makeOrder($user, ['payment_method' => 'fapshi'])->isOnlinePayment());
        $this->assertFalse($this->makeOrder($user, ['payment_method' => 'cash_on_delivery'])->isOnlinePayment());
        $this->assertFalse($this->makeOrder($user, ['payment_method' => 'selbuildi_credit'])->isOnlinePayment());
    }

    public function test_payment_method_labels(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->assertSame('Flutterwave', $this->makeOrder($user, ['payment_method' => 'flutterwave'])->paymentMethodLabel());
        $this->assertSame('Fapshi (MTN/Orange Money)', $this->makeOrder($user, ['payment_method' => 'fapshi'])->paymentMethodLabel());
        $this->assertSame('Cash / Pay on Delivery', $this->makeOrder($user, ['payment_method' => 'cash_on_delivery'])->paymentMethodLabel());
        $this->assertSame('Selbuildi Credit', $this->makeOrder($user, ['payment_method' => 'selbuildi_credit'])->paymentMethodLabel());
    }

    public function test_can_retry_payment_only_for_a_not_yet_paid_online_order_that_is_not_cancelled(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->assertTrue($this->makeOrder($user, ['payment_method' => 'flutterwave', 'payment_status' => 'failed'])->canRetryPayment());
        $this->assertTrue($this->makeOrder($user, ['payment_method' => 'flutterwave', 'payment_status' => 'pending'])->canRetryPayment());
        $this->assertFalse($this->makeOrder($user, ['payment_method' => 'flutterwave', 'payment_status' => 'paid'])->canRetryPayment());
        $this->assertFalse($this->makeOrder($user, ['payment_method' => 'flutterwave', 'payment_status' => 'failed', 'status' => 'cancelled'])->canRetryPayment());
        $this->assertFalse($this->makeOrder($user, ['payment_method' => 'cash_on_delivery', 'payment_status' => 'pending'])->canRetryPayment());
    }

    // --- Order page ---

    public function test_the_order_page_offers_a_retry_button_for_a_failed_online_payment(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user);
        $this->actingAs($user);

        Volt::test('orders.show', ['order' => $order])
            ->assertSee('Retry Payment')
            ->assertSee('Flutterwave')
            ->assertSee('Failed');
    }

    public function test_the_order_page_does_not_offer_a_retry_button_once_paid(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user, ['payment_status' => 'paid']);
        $this->actingAs($user);

        Volt::test('orders.show', ['order' => $order])
            ->assertDontSee('Retry Payment');
    }

    public function test_the_order_page_does_not_offer_a_retry_button_for_cash_on_delivery(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user, ['payment_method' => 'cash_on_delivery', 'payment_status' => 'pending']);
        $this->actingAs($user);

        Volt::test('orders.show', ['order' => $order])
            ->assertDontSee('Retry Payment')
            ->assertSee('Cash / Pay on Delivery');
    }

    // --- Retry action itself ---

    public function test_retrying_payment_creates_a_new_payment_and_redirects_to_the_provider(): void
    {
        PaymentGateway::create(['provider' => 'flutterwave', 'display_name' => 'Flutterwave', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test_abc']]);
        Http::fake(['api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/abc123'],
        ])]);

        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user);
        $this->actingAs($user);

        Volt::test('orders.show', ['order' => $order])
            ->call('retryPayment')
            ->assertRedirect('https://checkout.flutterwave.com/pay/abc123');

        $this->assertSame(1, $order->payments()->count());
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'flutterwave',
            'amount' => 9500,
            'status' => 'pending',
        ]);
    }

    public function test_retrying_payment_when_the_gateway_is_disabled_flashes_an_error_instead_of_crashing(): void
    {
        // No PaymentGateway row at all for flutterwave - simulates an
        // admin having disabled it since the order was placed. Checked
        // before a new Payment row is even created, so nothing is left
        // half-created.
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user);
        $this->actingAs($user);

        Volt::test('orders.show', ['order' => $order])
            ->call('retryPayment');

        $this->assertSame(0, $order->payments()->count());
    }

    public function test_a_customer_cannot_retry_payment_on_an_order_that_is_already_paid(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user, ['payment_status' => 'paid']);
        $this->actingAs($user);

        Volt::test('orders.show', ['order' => $order])
            ->call('retryPayment')
            ->assertStatus(403);
    }
}
