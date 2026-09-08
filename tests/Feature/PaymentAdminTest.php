<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makePaidOrder(int $amount = 9500): Payment
    {
        $user = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $user->id,
            'status' => 'delivered',
            'subtotal' => $amount,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => $amount,
            'currency' => 'XAF',
            'payment_status' => 'paid',
            'payment_method' => 'flutterwave',
            'placed_at' => now(),
        ]);

        return Payment::create([
            'order_id' => $order->id,
            'provider' => 'flutterwave',
            'amount' => $amount,
            'currency' => 'XAF',
            'status' => 'paid',
            'reference' => 'SB-TEST-'.uniqid(),
            'paid_at' => now(),
        ]);
    }

    public function test_admin_can_see_payments_list(): void
    {
        $payment = $this->makePaidOrder();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManagePayments::class)
            ->assertSee($payment->reference)
            ->assertSee($payment->order->order_number);
    }

    public function test_admin_can_record_a_refund_for_a_paid_payment(): void
    {
        Notification::fake();

        $payment = $this->makePaidOrder();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManagePayments::class)
            ->callTableAction('refund', $payment, data: ['note' => 'Customer requested a refund.']);

        $payment->refresh();
        $order = $payment->order->fresh();

        $this->assertSame('refunded', $payment->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('refunded', $order->status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status' => 'refunded',
        ]);
    }

    public function test_refund_action_is_not_available_for_a_pending_payment(): void
    {
        $payment = $this->makePaidOrder();
        $payment->update(['status' => 'pending']);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManagePayments::class)
            ->assertTableActionHidden('refund', $payment);
    }
}
