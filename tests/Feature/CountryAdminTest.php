<?php

namespace Tests\Feature;

use App\Filament\Resources\Countries\Pages\ManageCountries;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CountryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_country_is_not_available_for_checkout_by_default(): void
    {
        Country::create(['name' => 'Nigeria', 'code' => 'NG']);

        // Not passed above - pulling the DB column's own default, not
        // whatever an in-memory, never-refetched model happens to hold.
        $this->assertFalse(Country::where('code', 'NG')->sole()->checkout_enabled);
        $this->assertArrayNotHasKey('Nigeria', Country::checkoutOptions());
    }

    public function test_an_admin_can_enable_a_country_for_checkout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $country = Country::create(['name' => 'Nigeria', 'code' => 'NG']);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCountries::class)
            ->callTableAction('edit', $country, data: [
                'name' => 'Nigeria',
                'code' => 'NG',
                'checkout_enabled' => true,
            ]);

        $this->assertTrue($country->fresh()->checkout_enabled);
        $this->assertArrayHasKey('Nigeria', Country::checkoutOptions());
    }

    public function test_an_admin_can_disable_a_previously_enabled_country(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $country = Country::create(['name' => 'Nigeria', 'code' => 'NG', 'checkout_enabled' => true]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCountries::class)
            ->callTableAction('edit', $country, data: [
                'name' => 'Nigeria',
                'code' => 'NG',
                'checkout_enabled' => false,
            ]);

        $this->assertFalse($country->fresh()->checkout_enabled);
    }

    public function test_checkout_options_are_sorted_by_name(): void
    {
        // The base TestCase already seeds Cameroon as checkout-enabled.
        Country::create(['name' => 'Nigeria', 'code' => 'NG', 'checkout_enabled' => true]);
        Country::create(['name' => 'Ghana', 'code' => 'GH', 'checkout_enabled' => true]);

        $this->assertSame(['Cameroon', 'Ghana', 'Nigeria'], array_keys(Country::checkoutOptions()));
    }
}
