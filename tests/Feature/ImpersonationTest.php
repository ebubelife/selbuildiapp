<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_start_impersonating_a_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($admin, 'admin')
            ->get(route('impersonation.start', $customer))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($customer, 'web');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertSame($admin->id, session('impersonator_id'));
    }

    public function test_admin_accounts_cannot_be_impersonated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('impersonation.start', $otherAdmin))
            ->assertForbidden();
    }

    public function test_a_customer_session_cannot_start_impersonation(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $target = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get(route('impersonation.start', $target))
            ->assertRedirect();

        $this->assertAuthenticatedAs($customer, 'web');
    }

    public function test_stopping_impersonation_returns_the_admin_without_a_fresh_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($admin, 'admin')
            ->get(route('impersonation.start', $customer));

        $this->post(route('impersonation.stop'))
            ->assertRedirect('/s/admin/build');

        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNull(session('impersonator_id'));
    }
}
