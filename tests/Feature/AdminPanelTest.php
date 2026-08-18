<?php

namespace Tests\Feature;

use App\Filament\Resources\ContractorProfiles\Pages\ManageContractorProfiles;
use App\Filament\Resources\CreditAccounts\Pages\ManageCreditAccounts;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\SupplierProfiles\Pages\ManageSupplierProfiles;
use App\Models\CreditAccount;
use App\Models\ContractorProfile;
use App\Models\Order;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_access_the_panel(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        $this->get('/admin')->assertForbidden();
    }

    public function test_an_admin_can_access_the_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->get('/admin')->assertOk();
    }

    public function test_admin_can_verify_a_pending_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Test Supplier Co',
            'slug' => 'test-supplier-co-'.uniqid(),
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageSupplierProfiles::class)
            ->callTableAction('verify', $supplier);

        $this->assertNotNull($supplier->fresh()->verified_at);
    }

    public function test_admin_can_approve_a_pending_contractor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $contractor = ContractorProfile::create([
            'user_id' => User::factory()->create(['role' => 'contractor'])->id,
            'business_name' => 'Test Contracting Co',
            'verification_status' => 'pending',
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageContractorProfiles::class)
            ->callTableAction('approve', $contractor);

        $contractor->refresh();
        $this->assertSame('verified', $contractor->verification_status);
        $this->assertNotNull($contractor->verified_at);
    }

    public function test_admin_can_reject_a_pending_contractor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $contractor = ContractorProfile::create([
            'user_id' => User::factory()->create(['role' => 'contractor'])->id,
            'business_name' => 'Test Contracting Co',
            'verification_status' => 'pending',
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageContractorProfiles::class)
            ->callTableAction('reject', $contractor);

        $this->assertSame('rejected', $contractor->fresh()->verification_status);
    }

    public function test_admin_can_approve_a_credit_application_with_a_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $account = CreditAccount::create([
            'user_id' => $customer->id,
            'credit_limit' => 50000,
            'available_credit' => 0,
            'status' => 'pending',
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageCreditAccounts::class)
            ->callTableAction('approve', $account, data: ['limit' => 75000]);

        $account->refresh();
        $this->assertSame('approved', $account->status);
        $this->assertSame(75000, $account->credit_limit);
        $this->assertSame(75000, $account->available_credit);
    }

    public function test_admin_can_reject_a_credit_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $account = CreditAccount::create([
            'user_id' => $customer->id,
            'credit_limit' => 50000,
            'available_credit' => 0,
            'status' => 'pending',
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageCreditAccounts::class)
            ->callTableAction('reject', $account);

        $this->assertSame('rejected', $account->fresh()->status);
    }

    public function test_admin_can_update_an_orders_status_with_a_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'order_number' => 'SB-TEST-ADMIN-0001',
            'user_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => 9500,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 9500,
            'currency' => 'XAF',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'placed_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageOrders::class)
            ->callTableAction('updateStatus', $order, data: [
                'status' => 'confirmed',
                'note' => 'Confirmed by admin.',
            ]);

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status' => 'confirmed',
            'note' => 'Confirmed by admin.',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_orders_resource_has_no_create_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        Livewire::test(ManageOrders::class)
            ->assertActionDoesNotExist('create');
    }
}
