<?php

namespace Tests\Feature;

use App\Filament\Resources\DeliveryUpdateRequests\Pages\ManageDeliveryUpdateRequests;
use App\Filament\Resources\Shipments\Pages\ManageShipments;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentUpdateRequest;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Notifications\DeliveryAgentAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeliveryAgentPortalTest extends TestCase
{
    use RefreshDatabase;

    private function makeAgentUser(bool $active = true): User
    {
        $user = User::factory()->create(['role' => 'delivery_agent']);
        DeliveryAgent::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+237670000001',
            'is_active' => $active,
        ]);

        return $user->fresh();
    }

    private function makeShipment(?DeliveryAgent $agent = null): Shipment
    {
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Test Supplier',
            'slug' => 'test-supplier-'.uniqid(),
            'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Cement', 'slug' => 'cement-'.uniqid(), 'icon' => 'cement']);
        Product::create([
            'supplier_profile_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Cement 50kg',
            'slug' => 'cement-50kg-'.uniqid(),
            'sku' => 'SB-'.uniqid(),
            'unit' => 'bag',
            'price' => 4500,
            'min_order_qty' => 1,
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'status' => 'pending',
            'subtotal' => 4500,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 4500,
            'currency' => 'XAF',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'placed_at' => now(),
        ]);

        return Shipment::create([
            'order_id' => $order->id,
            'supplier_profile_id' => $supplier->id,
            'delivery_agent_id' => $agent?->id,
            'status' => 'pending',
        ]);
    }

    // --- Portal access ---

    public function test_a_non_agent_cannot_access_the_deliveries_portal(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer);

        Volt::test('deliveries.index')->assertForbidden();
    }

    public function test_an_unapproved_agent_sees_a_pending_banner_and_no_actions(): void
    {
        $agentUser = $this->makeAgentUser(active: false);
        $shipment = $this->makeShipment($agentUser->deliveryAgentProfile);

        $this->actingAs($agentUser);

        Volt::test('deliveries.index')
            ->assertSee('Approval pending')
            ->assertDontSee('Mark Delivered');
    }

    public function test_an_approved_agent_sees_their_assigned_shipments(): void
    {
        $agentUser = $this->makeAgentUser();
        $shipment = $this->makeShipment($agentUser->deliveryAgentProfile);

        $this->actingAs($agentUser);

        Volt::test('deliveries.index')
            ->assertSee($shipment->order->order_number)
            ->assertSee('Mark Delivered');
    }

    // --- Requesting updates (never mutates the shipment directly) ---

    public function test_requesting_out_for_delivery_creates_a_pending_request_without_changing_the_shipment(): void
    {
        $agentUser = $this->makeAgentUser();
        $shipment = $this->makeShipment($agentUser->deliveryAgentProfile);

        $this->actingAs($agentUser);

        Volt::test('deliveries.index')
            ->call('requestOutForDelivery', $shipment->id);

        $this->assertSame('pending', $shipment->fresh()->status);
        $this->assertDatabaseHas('shipment_update_requests', [
            'shipment_id' => $shipment->id,
            'requested_status' => 'out_for_delivery',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('activity_logs', ['type' => 'delivery_update_requested']);
    }

    public function test_marking_delivered_requires_a_proof_photo_and_creates_a_pending_request(): void
    {
        Storage::fake('public');

        $agentUser = $this->makeAgentUser();
        $shipment = $this->makeShipment($agentUser->deliveryAgentProfile);

        $this->actingAs($agentUser);

        $component = Volt::test('deliveries.index')
            ->call('startDelivered', $shipment->id)
            ->set('proofNote', 'Left with the site foreman.')
            ->call('confirmDelivered');

        $component->assertHasErrors(['proofPhoto']);
        $this->assertSame('pending', $shipment->fresh()->status);

        Volt::test('deliveries.index')
            ->call('startDelivered', $shipment->id)
            ->set('proofPhoto', UploadedFile::fake()->image('proof.jpg'))
            ->set('proofNote', 'Left with the site foreman.')
            ->call('confirmDelivered');

        $this->assertSame('pending', $shipment->fresh()->status, 'The shipment itself must not change until admin approves.');

        $request = ShipmentUpdateRequest::where('shipment_id', $shipment->id)->sole();
        $this->assertSame('delivered', $request->requested_status);
        $this->assertSame('pending', $request->status);
        $this->assertNotNull($request->photo_path);
        Storage::disk('public')->assertExists($request->photo_path);
    }

    public function test_an_agent_cannot_submit_a_second_request_while_one_is_pending(): void
    {
        $agentUser = $this->makeAgentUser();
        $shipment = $this->makeShipment($agentUser->deliveryAgentProfile);

        $this->actingAs($agentUser);

        $component = Volt::test('deliveries.index');
        $component->call('requestOutForDelivery', $shipment->id);
        $component->call('requestOutForDelivery', $shipment->id);

        $this->assertSame(1, ShipmentUpdateRequest::where('shipment_id', $shipment->id)->count());
    }

    public function test_an_agent_cannot_request_an_update_for_a_shipment_not_assigned_to_them(): void
    {
        $agentUser = $this->makeAgentUser();
        $otherAgent = DeliveryAgent::create(['name' => 'Other', 'email' => 'other@example.com', 'phone' => '+237600000002', 'is_active' => true]);
        $shipment = $this->makeShipment($otherAgent);

        $this->actingAs($agentUser);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Volt::test('deliveries.index')->call('requestOutForDelivery', $shipment->id);
    }

    // --- Admin review ---

    public function test_an_admin_can_approve_a_delivery_update_request_and_it_applies_to_the_shipment(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $agentUser = $this->makeAgentUser();
        $shipment = $this->makeShipment($agentUser->deliveryAgentProfile);

        $photoPath = UploadedFile::fake()->image('proof.jpg')->store('delivery-proofs', 'public');
        $request = ShipmentUpdateRequest::create([
            'shipment_id' => $shipment->id,
            'delivery_agent_id' => $agentUser->deliveryAgentProfile->id,
            'requested_status' => 'delivered',
            'note' => 'Left with the site foreman.',
            'photo_path' => $photoPath,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageDeliveryUpdateRequests::class)->callTableAction('approve', $request);

        $shipment->refresh();
        $this->assertSame('delivered', $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame('Left with the site foreman.', $shipment->proof_of_delivery_note);
        $this->assertSame($photoPath, $shipment->proof_photo_path);

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->reviewed_at);
        $this->assertDatabaseHas('activity_logs', ['type' => 'delivery_update_approved']);
    }

    public function test_an_admin_can_reject_a_delivery_update_request_without_changing_the_shipment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agentUser = $this->makeAgentUser();
        $shipment = $this->makeShipment($agentUser->deliveryAgentProfile);

        $request = ShipmentUpdateRequest::create([
            'shipment_id' => $shipment->id,
            'delivery_agent_id' => $agentUser->deliveryAgentProfile->id,
            'requested_status' => 'out_for_delivery',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageDeliveryUpdateRequests::class)
            ->callTableAction('reject', $request, data: ['reason' => 'Wrong shipment.']);

        $this->assertSame('pending', $shipment->fresh()->status);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['type' => 'delivery_update_rejected']);
    }

    // --- Assignment email ---

    public function test_assigning_a_shipment_emails_the_agents_linked_user(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $agentUser = $this->makeAgentUser();
        $shipment = $this->makeShipment();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageShipments::class)
            ->callTableAction('assignAgent', $shipment, data: ['delivery_agent_id' => $agentUser->deliveryAgentProfile->id]);

        Notification::assertSentTo($agentUser, DeliveryAgentAssigned::class);
    }

    public function test_assigning_a_shipment_to_a_roster_only_agent_does_not_error(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $rosterAgent = DeliveryAgent::create(['name' => 'Roster Agent', 'email' => 'roster@example.com', 'phone' => '+237600000003', 'is_active' => true]);
        $shipment = $this->makeShipment();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageShipments::class)
            ->callTableAction('assignAgent', $shipment, data: ['delivery_agent_id' => $rosterAgent->id]);

        $this->assertSame($rosterAgent->id, $shipment->fresh()->delivery_agent_id);
        Notification::assertNothingSent();
    }

    // --- Activity log / notifications tab ---

    public function test_admin_can_view_and_filter_the_activity_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ActivityLog::log('user_registered', 'Someone signed up.');
        ActivityLog::log('order_placed', 'Someone placed an order.');

        $this->actingAs($admin, 'admin');

        Livewire::test(\App\Filament\Resources\ActivityLogs\Pages\ManageActivityLogs::class)
            ->assertCanSeeTableRecords(ActivityLog::all())
            ->filterTable('type', 'order_placed')
            ->assertCanSeeTableRecords(ActivityLog::where('type', 'order_placed')->get())
            ->assertCanNotSeeTableRecords(ActivityLog::where('type', 'user_registered')->get());
    }
}
