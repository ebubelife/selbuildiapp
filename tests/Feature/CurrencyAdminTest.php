<?php

namespace Tests\Feature;

use App\Filament\Resources\Currencies\Pages\ManageCurrencies;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CurrencyAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_new_currency(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCurrencies::class)
            ->callAction('create', data: [
                'code' => 'cad',
                'name' => 'Canadian Dollar',
                'symbol' => 'C$',
                'country_label' => 'Canada',
                'rate_to_xaf' => 440,
                'is_supported' => true,
            ]);

        // Code is uppercased on the way in.
        $this->assertDatabaseHas('currencies', ['code' => 'CAD', 'rate_to_xaf' => 440]);
    }

    public function test_an_admin_can_edit_a_currencys_rate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $usd = Currency::where('code', 'USD')->sole();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCurrencies::class)
            ->callTableAction('edit', $usd, data: [
                'code' => 'USD',
                'name' => $usd->name,
                'symbol' => $usd->symbol,
                'country_label' => $usd->country_label,
                'rate_to_xaf' => 610,
                'is_supported' => true,
            ]);

        $this->assertSame('610.000000', (string) $usd->fresh()->rate_to_xaf);
    }

    public function test_xaf_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $xaf = Currency::where('code', 'XAF')->sole();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCurrencies::class)
            ->assertTableActionHidden('delete', $xaf);
    }

    public function test_a_non_xaf_currency_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $usd = Currency::where('code', 'USD')->sole();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCurrencies::class)
            ->callTableAction('delete', $usd);

        $this->assertDatabaseMissing('currencies', ['code' => 'USD']);
    }

    public function test_refresh_all_live_rates_updates_every_non_xaf_currency(): void
    {
        Http::fake(['open.er-api.com/*' => Http::response([
            'result' => 'success',
            'rates' => ['XAF' => 1, 'NGN' => 2.5, 'USD' => 0.0017],
        ])]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCurrencies::class)->callTableAction('refreshAllLiveRates');

        $ngn = Currency::where('code', 'NGN')->sole();
        $this->assertSame('0.400000', (string) $ngn->rate_to_xaf); // 1 / 2.5
        $this->assertSame('live', $ngn->rate_source);
        $this->assertNotNull($ngn->rate_fetched_at);

        // XAF itself is untouched - it's always exactly 1 by definition.
        $this->assertSame('1.000000', (string) Currency::where('code', 'XAF')->sole()->rate_to_xaf);
    }

    public function test_refresh_all_live_rates_handles_the_service_being_unreachable(): void
    {
        Http::fake(['open.er-api.com/*' => Http::response([], 500)]);

        $admin = User::factory()->create(['role' => 'admin']);
        $usd = Currency::where('code', 'USD')->sole();
        $originalRate = (string) $usd->rate_to_xaf;

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCurrencies::class)->callTableAction('refreshAllLiveRates');

        $this->assertSame($originalRate, (string) $usd->fresh()->rate_to_xaf);
    }
}
