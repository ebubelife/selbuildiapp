<?php

namespace Tests\Feature;

use App\Filament\Resources\CreditTierSettings\Pages\ManageCreditTierSettings;
use App\Models\CreditTierSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreditTierSettingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_four_tiers_are_seeded_by_the_migration(): void
    {
        $this->assertSame(
            CreditTierSetting::ORDER,
            CreditTierSetting::orderBy('id')->pluck('tier')->all()
        );
    }

    public function test_an_admin_can_see_the_tier_settings_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCreditTierSettings::class)
            ->assertSee('Gold')
            ->assertSee('Net-15 credit terms');
    }

    public function test_an_admin_can_edit_a_tiers_terms(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $gold = CreditTierSetting::where('tier', 'gold')->sole();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCreditTierSettings::class)
            ->callTableAction('edit', $gold, data: [
                'perk_headline' => 'Net-20 credit terms',
                'auto_approve_limit' => 300000,
                'net_terms_days' => 20,
                'deposit_percentage' => null,
            ]);

        $gold->refresh();
        $this->assertSame('Net-20 credit terms', $gold->perk_headline);
        $this->assertSame(300000, $gold->auto_approve_limit);
        $this->assertSame(20, $gold->net_terms_days);
    }

    public function test_the_resource_has_no_create_or_delete_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $gold = CreditTierSetting::where('tier', 'gold')->sole();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCreditTierSettings::class)
            ->assertActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('delete', record: $gold);
    }
}
