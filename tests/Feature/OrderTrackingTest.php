<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusUpdated;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
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

    private function placeOrderFor(User $user): Order
    {
        $this->actingAs($user);
        $product = $this->createProduct();
        app(CartService::class)->add($product, 2);

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

        return Order::sole();
    }

    public function test_placing_an_order_notifies_the_customer(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->placeOrderFor($user);

        Notification::assertSentTo($user, OrderPlaced::class, function (OrderPlaced $notification) use ($order) {
            return $notification->order->id === $order->id;
        });
    }

    public function test_update_status_command_advances_order_and_notifies_customer(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->placeOrderFor($user);

        Artisan::call('orders:update-status', [
            'order' => $order->order_number,
            'status' => 'confirmed',
            '--note' => 'Supplier confirmed stock availability.',
        ]);

        $order->refresh();

        $this->assertSame('confirmed', $order->status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status' => 'confirmed',
            'note' => 'Supplier confirmed stock availability.',
            'changed_by' => null,
        ]);

        Notification::assertSentTo($user, OrderStatusUpdated::class, function (OrderStatusUpdated $notification) use ($order) {
            return $notification->order->id === $order->id;
        });
    }

    public function test_update_status_command_rejects_invalid_status(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->placeOrderFor($user);

        $exitCode = Artisan::call('orders:update-status', [
            'order' => $order->order_number,
            'status' => 'not-a-real-status',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_update_status_command_fails_gracefully_for_unknown_order(): void
    {
        $exitCode = Artisan::call('orders:update-status', [
            'order' => 'SB-DOES-NOT-EXIST',
            'status' => 'confirmed',
        ]);

        $this->assertSame(1, $exitCode);
    }

    public function test_orders_index_lists_only_the_authenticated_users_orders(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $order = $this->placeOrderFor($owner);

        $stranger = User::factory()->create(['role' => 'customer']);
        $this->actingAs($stranger);

        Volt::test('orders.index')
            ->assertDontSee($order->order_number);

        $this->actingAs($owner);

        Volt::test('orders.index')
            ->assertSee($order->order_number);
    }

    public function test_orders_index_shows_empty_state_with_no_orders(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        Volt::test('orders.index')
            ->assertSee('No orders yet');
    }

    public function test_dashboard_shows_recent_orders_for_customer(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->placeOrderFor($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('1 order placed so far');
    }

    public function test_dashboard_shows_empty_state_copy_for_customer_with_no_orders(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your orders, delivery tracking, and Procurement Trust Score will show up here');
    }
}
