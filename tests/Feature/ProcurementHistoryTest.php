<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Services\TrustScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProcurementHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_view_a_customers_procurement_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $service = app(TrustScoreService::class);
        $service->recordEvent($customer, 'order_completed');
        $service->recordEvent($customer, 'payment_failed');

        $this->actingAs($admin, 'admin');

        // Mounting the view action renders the Procurement History
        // infolist schema (Section/RepeatableEntry/TextEntry) against the
        // customer's real trustScore + trustScoreEvents relations - it
        // would throw if that schema were broken (same pattern as
        // OrderResource's status-history view test).
        Livewire::test(ManageUsers::class)
            ->mountTableAction('view', $customer)
            ->assertOk();
    }

    public function test_events_show_oldest_first(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $service = app(TrustScoreService::class);

        $service->recordEvent($customer, 'kyc_verified');
        $service->recordEvent($customer, 'order_completed');

        $events = $customer->trustScoreEvents;

        $this->assertSame('kyc_verified', $events->first()->event_type);
        $this->assertSame('order_completed', $events->last()->event_type);
    }
}
