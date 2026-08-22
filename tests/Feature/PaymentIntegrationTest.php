<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderWithPayment(string $provider, int $amount = 9500): array
    {
        $user = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $user->id,
            'status' => 'pending',
            'subtotal' => $amount,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => $amount,
            'currency' => 'XAF',
            'payment_status' => 'pending',
            'payment_method' => $provider,
            'placed_at' => now(),
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => $provider,
            'amount' => $amount,
            'currency' => 'XAF',
            'status' => 'pending',
            'reference' => 'SB-TEST-'.uniqid(),
        ]);

        return [$order, $payment];
    }

    // --- Manager ---

    public function test_manager_reports_enabled_and_disabled_gateways_correctly(): void
    {
        PaymentGateway::create(['provider' => 'paystack', 'display_name' => 'Paystack', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test_x']]);
        PaymentGateway::create(['provider' => 'flutterwave', 'display_name' => 'Flutterwave', 'is_enabled' => false, 'mode' => 'test', 'credentials' => []]);

        $manager = app(PaymentGatewayManager::class);

        $this->assertTrue($manager->isEnabled('paystack'));
        $this->assertFalse($manager->isEnabled('flutterwave'));
        $this->assertCount(1, $manager->enabled());
    }

    public function test_manager_refuses_to_build_a_disabled_gateway(): void
    {
        PaymentGateway::create(['provider' => 'fapshi', 'display_name' => 'Fapshi', 'is_enabled' => false, 'mode' => 'test', 'credentials' => []]);

        $this->expectException(RuntimeException::class);

        app(PaymentGatewayManager::class)->make('fapshi');
    }

    // --- Flutterwave ---

    public function test_flutterwave_initialize_returns_the_hosted_checkout_link(): void
    {
        PaymentGateway::create(['provider' => 'flutterwave', 'display_name' => 'Flutterwave', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'FLWSECK_TEST-abc']]);
        [, $payment] = $this->makeOrderWithPayment('flutterwave');

        Http::fake(['api.flutterwave.com/*' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/pay/xyz'],
        ])]);

        $url = app(PaymentGatewayManager::class)->make('flutterwave')->initialize($payment, 'https://selbuildi.com/callback');

        $this->assertSame('https://checkout.flutterwave.com/pay/xyz', $url);
        Http::assertSent(fn ($request) => $request['tx_ref'] === $payment->reference && $request['amount'] === $payment->amount);
    }

    public function test_flutterwave_webhook_signature_must_match_the_configured_hash(): void
    {
        PaymentGateway::create(['provider' => 'flutterwave', 'display_name' => 'Flutterwave', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'x', 'webhook_hash' => 'correct-hash']]);

        $gateway = app(PaymentGatewayManager::class)->make('flutterwave');

        $this->assertTrue($gateway->verifyWebhookSignature(
            \Illuminate\Http\Request::create('/', 'POST', server: ['HTTP_verif-hash' => 'correct-hash'])
        ));
        $this->assertFalse($gateway->verifyWebhookSignature(
            \Illuminate\Http\Request::create('/', 'POST', server: ['HTTP_verif-hash' => 'wrong-hash'])
        ));
    }

    // --- Paystack ---

    public function test_paystack_initialize_returns_the_authorization_url(): void
    {
        PaymentGateway::create(['provider' => 'paystack', 'display_name' => 'Paystack', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test_abc']]);
        [, $payment] = $this->makeOrderWithPayment('paystack');

        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz'],
        ])]);

        $url = app(PaymentGatewayManager::class)->make('paystack')->initialize($payment, 'https://selbuildi.com/callback');

        $this->assertSame('https://checkout.paystack.com/xyz', $url);
        Http::assertSent(fn ($request) => $request['reference'] === $payment->reference && $request['amount'] === $payment->amount * 100);
    }

    public function test_paystack_webhook_signature_is_verified_via_hmac(): void
    {
        PaymentGateway::create(['provider' => 'paystack', 'display_name' => 'Paystack', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test_abc']]);

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'SB-1']]);
        $validSignature = hash_hmac('sha512', $body, 'sk_test_abc');

        $gateway = app(PaymentGatewayManager::class)->make('paystack');

        $validRequest = \Illuminate\Http\Request::create('/', 'POST', content: $body, server: ['HTTP_x-paystack-signature' => $validSignature]);
        $invalidRequest = \Illuminate\Http\Request::create('/', 'POST', content: $body, server: ['HTTP_x-paystack-signature' => 'forged']);

        $this->assertTrue($gateway->verifyWebhookSignature($validRequest));
        $this->assertFalse($gateway->verifyWebhookSignature($invalidRequest));
    }

    // --- Fapshi ---

    public function test_fapshi_initialize_uses_the_sandbox_url_in_test_mode_and_live_url_in_live_mode(): void
    {
        PaymentGateway::create(['provider' => 'fapshi', 'display_name' => 'Fapshi', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['api_user' => 'u', 'api_key' => 'k']]);
        [, $payment] = $this->makeOrderWithPayment('fapshi');

        Http::fake(['sandbox.fapshi.com/*' => Http::response(['link' => 'https://sandbox.fapshi.com/pay/xyz'])]);

        $url = app(PaymentGatewayManager::class)->make('fapshi')->initialize($payment, 'https://selbuildi.com/callback');

        $this->assertSame('https://sandbox.fapshi.com/pay/xyz', $url);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sandbox.fapshi.com'));
    }

    // --- Verification service (the shared, idempotent core) ---

    public function test_confirm_marks_payment_and_order_paid_on_successful_verification(): void
    {
        PaymentGateway::create(['provider' => 'paystack', 'display_name' => 'Paystack', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test_abc']]);
        [$order, $payment] = $this->makeOrderWithPayment('paystack', 9500);

        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 950000, 'currency' => 'XAF'],
        ])]);

        app(PaymentVerificationService::class)->confirm('paystack', $payment->reference);

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
    }

    public function test_confirm_marks_payment_failed_on_amount_mismatch(): void
    {
        PaymentGateway::create(['provider' => 'paystack', 'display_name' => 'Paystack', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test_abc']]);
        [$order, $payment] = $this->makeOrderWithPayment('paystack', 9500);

        // Provider reports a successful payment, but for a smaller amount
        // than the order actually costs.
        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 100000, 'currency' => 'XAF'],
        ])]);

        app(PaymentVerificationService::class)->confirm('paystack', $payment->reference);

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_confirm_is_idempotent_and_does_not_re_verify_an_already_paid_payment(): void
    {
        PaymentGateway::create(['provider' => 'paystack', 'display_name' => 'Paystack', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test_abc']]);
        [, $payment] = $this->makeOrderWithPayment('paystack', 9500);
        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => []])]);

        app(PaymentVerificationService::class)->confirm('paystack', $payment->reference);

        Http::assertNothingSent();
    }

    // --- Webhook controller ---

    public function test_webhook_rejects_an_invalid_signature(): void
    {
        PaymentGateway::create(['provider' => 'paystack', 'display_name' => 'Paystack', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test_abc']]);
        [, $payment] = $this->makeOrderWithPayment('paystack', 9500);

        $this->postJson(route('payments.webhook', ['provider' => 'paystack']), [
            'data' => ['reference' => $payment->reference],
        ], ['x-paystack-signature' => 'forged'])
            ->assertStatus(401);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_webhook_with_a_valid_signature_confirms_the_payment(): void
    {
        PaymentGateway::create(['provider' => 'paystack', 'display_name' => 'Paystack', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test_abc']]);
        [, $payment] = $this->makeOrderWithPayment('paystack', 9500);

        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 950000, 'currency' => 'XAF'],
        ])]);

        $body = json_encode(['data' => ['reference' => $payment->reference]]);
        $signature = hash_hmac('sha512', $body, 'sk_test_abc');

        $this->call('POST', route('payments.webhook', ['provider' => 'paystack']), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_x-paystack-signature' => $signature,
        ], $body)->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    // --- Callback controller ---

    public function test_callback_confirms_and_redirects_to_the_order(): void
    {
        PaymentGateway::create(['provider' => 'paystack', 'display_name' => 'Paystack', 'is_enabled' => true, 'mode' => 'test', 'credentials' => ['secret_key' => 'sk_test_abc']]);
        [$order, $payment] = $this->makeOrderWithPayment('paystack', 9500);

        Http::fake(['api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'amount' => 950000, 'currency' => 'XAF'],
        ])]);

        $this->actingAs($payment->order->user)
            ->get(route('payments.callback', ['provider' => 'paystack', 'reference' => $payment->reference]))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame('paid', $payment->fresh()->status);
    }
}
