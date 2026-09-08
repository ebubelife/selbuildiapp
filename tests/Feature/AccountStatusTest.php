<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'is_active' => false]);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors(['form.email']);

        $this->assertGuest();
    }

    public function test_an_active_users_login_still_works(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivating_a_logged_in_user_ends_their_session_on_the_next_request(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $this->actingAs($user);

        // Deactivated after the session already started.
        $user->update(['is_active' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_deactivated_admin_cannot_access_the_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => false]);

        $this->actingAs($admin, 'admin')
            ->get('/s/admin/build')
            ->assertForbidden();
    }

    public function test_an_admin_can_deactivate_and_reactivate_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageUsers::class)
            ->callTableAction('toggleActive', $customer);

        $this->assertFalse($customer->fresh()->is_active);

        Livewire::test(ManageUsers::class)
            ->callTableAction('toggleActive', $customer);

        $this->assertTrue($customer->fresh()->is_active);
    }
}
