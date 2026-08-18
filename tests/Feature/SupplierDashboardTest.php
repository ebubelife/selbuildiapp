<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SupplierDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedSupplier(): User
    {
        $user = User::factory()->create(['role' => 'supplier']);

        SupplierProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Douala Building Depot',
            'slug' => 'douala-building-depot-'.uniqid(),
            'verified_at' => now(),
        ]);

        return $user;
    }

    private function category(): Category
    {
        return Category::create([
            'name' => 'Roofing',
            'slug' => 'roofing-'.uniqid(),
            'icon' => 'roofing',
        ]);
    }

    private function createProductFor(User $supplierUser, int $price = 9500): Product
    {
        return $supplierUser->supplierProfile->products()->create([
            'category_id' => $this->category()->id,
            'name' => 'Aluminium Roofing Sheet',
            'slug' => 'aluminium-roofing-sheet-'.uniqid(),
            'sku' => 'ROOF-'.uniqid(),
            'unit' => 'piece',
            'price' => $price,
            'min_order_qty' => 1,
            'is_active' => true,
        ]);
    }

    public function test_a_non_supplier_cannot_access_the_products_index(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        Volt::test('supplier.products.index')->assertStatus(403);
    }

    public function test_an_unverified_supplier_cannot_create_a_product(): void
    {
        $user = User::factory()->create(['role' => 'supplier']);
        SupplierProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Pending Supplier',
            'slug' => 'pending-supplier-'.uniqid(),
        ]);
        $this->actingAs($user);

        Volt::test('supplier.products.form')->assertStatus(403);
    }

    public function test_a_verified_supplier_can_create_a_product_with_stock(): void
    {
        Storage::fake('public');

        $user = $this->verifiedSupplier();
        $this->actingAs($user);

        Volt::test('supplier.products.form')
            ->set('name', 'Portland Cement 50kg')
            ->set('category_id', $this->category()->id)
            ->set('unit', 'bag')
            ->set('price', 4500)
            ->set('min_order_qty', 1)
            ->set('quantity_available', 100)
            ->set('image', UploadedFile::fake()->image('cement.jpg'))
            ->call('save')
            ->assertRedirect(route('supplier.products.index'));

        $product = Product::where('name', 'Portland Cement 50kg')->sole();
        $this->assertSame($user->supplierProfile->id, $product->supplier_profile_id);
        $this->assertSame(100, $product->inventories->sum('quantity_available'));
        $this->assertCount(1, $product->images);
        Storage::disk('public')->assertExists($product->images->first()->path);

        // A default warehouse should have been auto-provisioned.
        $this->assertSame(1, $user->supplierProfile->warehouses()->count());
    }

    public function test_a_supplier_can_edit_their_own_product(): void
    {
        $user = $this->verifiedSupplier();
        $product = $this->createProductFor($user);
        $this->actingAs($user);

        Volt::test('supplier.products.form', ['product' => $product])
            ->set('name', 'Updated Roofing Sheet Name')
            ->set('price', 11000)
            ->call('save')
            ->assertRedirect(route('supplier.products.index'));

        $this->assertSame('Updated Roofing Sheet Name', $product->fresh()->name);
        $this->assertSame(11000, $product->fresh()->price);
    }

    public function test_a_supplier_cannot_edit_another_suppliers_product(): void
    {
        $owner = $this->verifiedSupplier();
        $product = $this->createProductFor($owner);

        $intruder = $this->verifiedSupplier();
        $this->actingAs($intruder);

        Volt::test('supplier.products.form', ['product' => $product])->assertStatus(403);
    }

    public function test_a_supplier_can_toggle_a_products_active_state(): void
    {
        $user = $this->verifiedSupplier();
        $product = $this->createProductFor($user);
        $this->actingAs($user);

        Volt::test('supplier.products.index')->call('toggleActive', $product->id);

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_a_supplier_can_delete_their_own_product(): void
    {
        $user = $this->verifiedSupplier();
        $product = $this->createProductFor($user);
        $this->actingAs($user);

        Volt::test('supplier.products.index')->call('deleteProduct', $product->id);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_supplier_orders_index_only_shows_orders_containing_their_items(): void
    {
        $supplier = $this->verifiedSupplier();
        $otherSupplier = $this->verifiedSupplier();

        $product = $this->createProductFor($supplier);
        $otherProduct = $this->createProductFor($otherSupplier);

        $customer = User::factory()->create(['role' => 'customer']);

        $myOrder = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id, 'SB-MINE-0001');
        $otherOrder = $this->makeOrderWithItem($customer, $otherProduct, $otherSupplier->supplierProfile->id, 'SB-OTHER-0001');

        $this->actingAs($supplier);

        Volt::test('supplier.orders.index')
            ->assertSee('SB-MINE-0001')
            ->assertDontSee('SB-OTHER-0001');
    }

    public function test_advancing_an_item_on_a_single_supplier_order_cascades_to_order_status(): void
    {
        $supplier = $this->verifiedSupplier();
        $product = $this->createProductFor($supplier);
        $customer = User::factory()->create(['role' => 'customer']);

        $order = $this->makeOrderWithItem($customer, $product, $supplier->supplierProfile->id, 'SB-SINGLE-0001');
        $item = $order->items->first();

        $this->actingAs($supplier);

        Volt::test('supplier.orders.index')->call('advance', $item->id, 'confirmed');

        $this->assertSame('confirmed', $item->fresh()->fulfillment_status);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'status' => 'confirmed']);
    }

    public function test_advancing_an_item_on_a_multi_supplier_order_does_not_cascade_order_status(): void
    {
        $supplierA = $this->verifiedSupplier();
        $supplierB = $this->verifiedSupplier();
        $productA = $this->createProductFor($supplierA);
        $productB = $this->createProductFor($supplierB);
        $customer = User::factory()->create(['role' => 'customer']);

        $order = Order::create([
            'order_number' => 'SB-MULTI-0001',
            'user_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => $productA->price + $productB->price,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => $productA->price + $productB->price,
            'currency' => 'XAF',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'placed_at' => now(),
        ]);

        $itemA = $order->items()->create([
            'product_id' => $productA->id,
            'supplier_profile_id' => $supplierA->supplierProfile->id,
            'product_name' => $productA->name,
            'quantity' => 1,
            'unit_price' => $productA->price,
            'total_price' => $productA->price,
        ]);
        $order->items()->create([
            'product_id' => $productB->id,
            'supplier_profile_id' => $supplierB->supplierProfile->id,
            'product_name' => $productB->name,
            'quantity' => 1,
            'unit_price' => $productB->price,
            'total_price' => $productB->price,
        ]);

        $this->actingAs($supplierA);

        Volt::test('supplier.orders.index')->call('advance', $itemA->id, 'confirmed');

        $this->assertSame('confirmed', $itemA->fresh()->fulfillment_status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    private function makeOrderWithItem(User $customer, Product $product, int $supplierProfileId, string $orderNumber): Order
    {
        $order = Order::create([
            'order_number' => $orderNumber,
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

        return $order->load('items');
    }
}
