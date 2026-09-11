<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CurrencySwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_supported_currencies(): void
    {
        Category::create(['name' => 'Cement', 'slug' => 'cement', 'icon' => 'cement']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('NGN')
            ->assertSee('USD');
    }

    public function test_switching_currency_updates_the_session_and_the_account(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        Volt::test('currency-switcher')->call('switchCurrency', 'NGN');

        $this->assertSame('NGN', session('currency_code'));
        $this->assertSame('NGN', $user->fresh()->preferred_currency);
    }

    public function test_a_guest_can_switch_currency_for_their_session_only(): void
    {
        Volt::test('currency-switcher')->call('switchCurrency', 'GBP');

        $this->assertSame('GBP', session('currency_code'));
    }
}
