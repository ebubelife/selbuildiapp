<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_logs(): void
    {
        $this->get('/s/admin/build/logs')->assertForbidden();
    }

    public function test_a_customer_on_the_storefront_guard_cannot_view_logs(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        // Deliberately authenticated on 'web', not 'admin' - this must not
        // be enough, since the gate checks the 'admin' guard specifically.
        $this->actingAs($customer)
            ->get('/s/admin/build/logs')
            ->assertForbidden();
    }

    public function test_an_admin_on_the_admin_guard_can_view_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get('/s/admin/build/logs')
            ->assertOk();
    }
}
