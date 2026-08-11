<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame('customer', auth()->user()->role);
    }

    public function test_new_suppliers_can_register_with_a_pending_supplier_profile(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('role', 'supplier')
            ->set('name', 'Test Supplier')
            ->set('business_name', 'Test Building Supplies')
            ->set('email', 'supplier@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = auth()->user();
        $this->assertSame('supplier', $user->role);
        $this->assertNotNull($user->supplierProfile);
        $this->assertSame('Test Building Supplies', $user->supplierProfile->business_name);
        $this->assertFalse($user->supplierProfile->isVerified());
    }

    public function test_new_contractors_can_register_without_a_business_name(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('role', 'contractor')
            ->set('name', 'Test Contractor')
            ->set('email', 'contractor@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = auth()->user();
        $this->assertSame('contractor', $user->role);
        $this->assertTrue($user->isContractor());
        $this->assertNull($user->supplierProfile);
    }

    public function test_supplier_registration_requires_a_business_name(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('role', 'supplier')
            ->set('name', 'Test Supplier')
            ->set('email', 'supplier@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertHasErrors(['business_name' => 'required']);
        $this->assertGuest();
    }
}
