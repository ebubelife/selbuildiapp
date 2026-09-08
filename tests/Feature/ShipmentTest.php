<?php

namespace Tests\Feature;

use App\Filament\Resources\Shipments\Pages\ManageShipments;
use App\Filament\Widgets\DeliveryPerformanceWidget;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedSupplier(string $name = 'Douala Building Depot'): User
    {
        $user = User::factory()->create(['role' => 'supplier']);

        SupplierProfile::create([
            'user_id' => $user->id,
            'business_name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'verified_at' => now(),
        ]);

        return $user;
    }

    private function createProductFor(User $supplierUser, int $price = 9500): Product
    {
        $category = Category::create(['name' => 'Roofing', 'slug' => 'roofing-'.uniqid(), 'icon' => 'roofing']);

        return $supplierUser->supplierProfile->products()->create([
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

    private function makeOrderWithItem(User $customer, Product $product, int $supplierProfileId): Order
    {
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => $product->price,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => $product->price,
            'currency' => 'XAF',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'placed_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'supplier_profile_id' => $supplierProfileId,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $product->price,
            'total_price' => $product->price,
        ]);

        return $order;
    }

    public function test_checkout_creates_one_shipment_per_distinct_supplier(): void
    {
        $supplierA = $this->verifiedSupplier('Supplier A');
        $supplierB = $this->verifiedSupplier('Supplier B');
        $productA = $this->createProductFor($supplierA);
        $productB = $this->createProductFor($supplierB);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer);
        app(\App\Services\CartService::class)->add($productA, 1);
        app(\App\Services\CartService::class)->add($productB, 1);

        $address = $customer->addresses()->create([
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

        $order = Order::sole();

        $this->assertSame(2, $order->shipments()->count());
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'supplier_profile_id' => $supplierA->supplierProfile->id]);
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'supplier_profile_id' => $supplierB->supplierProfile->id]);
    }

    public function test_advancing_an_item_to_shipped_stamps_the_shipments_dispatched_at(): void
    {
        $supplier = $this->verifiedSupplier();
        $product = $this->createProductFor($supplier);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id);
        $item = $order->items->first();

        app(OrderFulfillmentService::class)->advanceItemStatus($item, 'shipped', $supplier);

        $shipment = Shipment::where('order_id', $order->id)->sole();
        $this->assertSame('shipped', $shipment->status);
        $this->assertNotNull($shipment->dispatched_at);
        $this->assertNull($shipment->delivered_at);
    }

    public function test_advancing_an_item_to_delivered_stamps_the_shipments_delivered_at(): void
    {
        $supplier = $this->verifiedSupplier();
        $product = $this->createProductFor($supplier);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id);
        $item = $order->items->first();

        $service = app(OrderFulfillmentService::class);
        $service->advanceItemStatus($item, 'shipped', $supplier);
        $service->advanceItemStatus($item->fresh(), 'delivered', $supplier);

        $shipment = Shipment::where('order_id', $order->id)->sole();
        $this->assertSame('delivered', $shipment->status);
        $this->assertNotNull($shipment->dispatched_at);
        $this->assertNotNull($shipment->delivered_at);
    }

    public function test_dispatched_at_is_not_overwritten_by_a_later_status_change(): void
    {
        $supplier = $this->verifiedSupplier();
        $product = $this->createProductFor($supplier);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id);
        $item = $order->items->first();

        $service = app(OrderFulfillmentService::class);
        $service->advanceItemStatus($item, 'shipped', $supplier);
        $firstDispatchedAt = Shipment::where('order_id', $order->id)->sole()->dispatched_at;

        $this->travel(1)->hours();
        $service->advanceItemStatus($item->fresh(), 'shipped', $supplier);

        $this->assertTrue($firstDispatchedAt->equalTo(Shipment::where('order_id', $order->id)->sole()->dispatched_at));
    }

    public function test_admin_forced_order_status_change_syncs_all_shipments(): void
    {
        $supplierA = $this->verifiedSupplier('Supplier A');
        $supplierB = $this->verifiedSupplier('Supplier B');
        $productA = $this->createProductFor($supplierA);
        $productB = $this->createProductFor($supplierB);
        $customer = User::factory()->create(['role' => 'customer']);

        $order = $this->makeOrderWithItem($customer, $productA, $supplierA->supplierProfile->id);
        $order->items()->create([
            'product_id' => $productB->id,
            'supplier_profile_id' => $supplierB->supplierProfile->id,
            'product_name' => $productB->name,
            'quantity' => 1,
            'unit_price' => $productB->price,
            'total_price' => $productB->price,
        ]);

        Shipment::create(['order_id' => $order->id, 'supplier_profile_id' => $supplierA->supplierProfile->id]);
        Shipment::create(['order_id' => $order->id, 'supplier_profile_id' => $supplierB->supplierProfile->id]);

        app(OrderFulfillmentService::class)->advanceOrderStatus($order, 'shipped');

        $this->assertSame(2, Shipment::where('order_id', $order->id)->where('status', 'shipped')->count());
        $this->assertSame(2, Shipment::where('order_id', $order->id)->whereNotNull('dispatched_at')->count());
    }

    public function test_is_on_time_is_null_until_both_dates_exist(): void
    {
        $shipment = new Shipment(['status' => 'shipped']);
        $this->assertNull($shipment->isOnTime());
    }

    public function test_is_on_time_compares_delivered_at_to_expected_delivery_at(): void
    {
        $onTime = new Shipment([
            'expected_delivery_at' => now()->addDay(),
            'delivered_at' => now(),
        ]);
        $late = new Shipment([
            'expected_delivery_at' => now()->subDay(),
            'delivered_at' => now(),
        ]);

        $this->assertTrue($onTime->isOnTime());
        $this->assertFalse($late->isOnTime());
    }

    // --- Admin resource ---

    public function test_admin_can_see_shipments_list(): void
    {
        $supplier = $this->verifiedSupplier();
        $product = $this->createProductFor($supplier);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id);
        $shipment = Shipment::create(['order_id' => $order->id, 'supplier_profile_id' => $supplier->supplierProfile->id]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManageShipments::class)
            ->assertSee($order->order_number)
            ->assertSee('Douala Building Depot');
    }

    public function test_admin_can_update_logistics_info(): void
    {
        $supplier = $this->verifiedSupplier();
        $product = $this->createProductFor($supplier);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id);
        $shipment = Shipment::create(['order_id' => $order->id, 'supplier_profile_id' => $supplier->supplierProfile->id]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManageShipments::class)
            ->callTableAction('updateLogistics', $shipment, data: [
                'carrier' => 'Bolloré Logistics',
                'tracking_reference' => 'TRK-12345',
            ]);

        $shipment->refresh();
        $this->assertSame('Bolloré Logistics', $shipment->carrier);
        $this->assertSame('TRK-12345', $shipment->tracking_reference);
    }

    public function test_admin_can_mark_a_shipment_dispatched_and_delivered(): void
    {
        $supplier = $this->verifiedSupplier();
        $product = $this->createProductFor($supplier);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id);
        $shipment = Shipment::create(['order_id' => $order->id, 'supplier_profile_id' => $supplier->supplierProfile->id]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManageShipments::class)->callTableAction('markDispatched', $shipment);
        $shipment->refresh();
        $this->assertSame('shipped', $shipment->status);
        $this->assertNotNull($shipment->dispatched_at);

        Livewire::test(ManageShipments::class)
            ->callTableAction('markDelivered', $shipment, data: ['proof_of_delivery_note' => 'Received by site foreman.']);
        $shipment->refresh();
        $this->assertSame('delivered', $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame('Received by site foreman.', $shipment->proof_of_delivery_note);
    }

    // --- Delivery performance widget ---

    public function test_delivery_performance_widget_computes_transit_time_and_on_time_rate(): void
    {
        $supplier = $this->verifiedSupplier();
        $product = $this->createProductFor($supplier);
        $customer = User::factory()->create(['role' => 'customer']);

        $onTimeOrder = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id);
        Shipment::create([
            'order_id' => $onTimeOrder->id,
            'supplier_profile_id' => $supplier->supplierProfile->id,
            'status' => 'delivered',
            'dispatched_at' => now()->subDays(2),
            'expected_delivery_at' => now(),
            'delivered_at' => now()->subDay(),
        ]);

        $lateOrder = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id);
        Shipment::create([
            'order_id' => $lateOrder->id,
            'supplier_profile_id' => $supplier->supplierProfile->id,
            'status' => 'delivered',
            'dispatched_at' => now()->subDays(4),
            'expected_delivery_at' => now()->subDays(2),
            'delivered_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(DeliveryPerformanceWidget::class)
            ->assertSee('Douala Building Depot')
            ->assertSee('50%'); // one of the two deliveries was on time
    }

    public function test_delivery_performance_widget_excludes_suppliers_with_no_deliveries(): void
    {
        $supplier = $this->verifiedSupplier();
        $product = $this->createProductFor($supplier);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id);
        Shipment::create(['order_id' => $order->id, 'supplier_profile_id' => $supplier->supplierProfile->id, 'status' => 'pending']);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(DeliveryPerformanceWidget::class)
            ->assertDontSee('Douala Building Depot');
    }
}
