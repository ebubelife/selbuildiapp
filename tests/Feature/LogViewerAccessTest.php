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
        // 'auth:admin' middleware (unlike the old Gate-based check) redirects
        // an unauthenticated request rather than 403ing - same behavior as
        // every other 'auth:admin'-protected route in this app (e.g.
        // impersonation).
        $this->get('/s/admin/build/logs')->assertRedirect();
    }

    public function test_a_customer_on_the_storefront_guard_cannot_view_logs(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        // Deliberately authenticated on 'web', not 'admin' - this must not
        // be enough, since 'auth:admin' checks the 'admin' guard specifically.
        $this->actingAs($customer)
            ->get('/s/admin/build/logs')
            ->assertRedirect();
    }

    public function test_an_admin_on_the_admin_guard_can_view_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get('/s/admin/build/logs')
            ->assertOk();
    }

    public function test_the_files_api_endpoint_is_also_protected_by_the_admin_guard(): void
    {
        // This is the actual endpoint the UI calls via AJAX to populate the
        // file list/content after the page loads - it has its own,
        // separately-configured middleware (api_middleware), so it needs
        // its own coverage distinct from the page route above.
        $this->get('/s/admin/build/logs/api/files')->assertRedirect();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get('/s/admin/build/logs/api/files')
            ->assertOk();
    }
}
