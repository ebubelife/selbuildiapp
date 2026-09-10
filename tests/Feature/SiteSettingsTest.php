<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\Category;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_and_set_round_trip_and_survive_the_cache(): void
    {
        $this->assertNull(SiteSetting::get('whatsapp_number'));

        // Prime the cache with the "missing" state, then write.
        SiteSetting::get('whatsapp_number');
        SiteSetting::set('whatsapp_number', '237670000000');

        $this->assertSame('237670000000', SiteSetting::get('whatsapp_number'));
    }

    public function test_an_admin_can_save_the_whatsapp_number_and_it_is_normalised(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'whatsapp_number' => '+237 670 00 00 00',
                'whatsapp_message' => '  Hi Selbuildi  ',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('237670000000', SiteSetting::get('whatsapp_number'));
        $this->assertSame('Hi Selbuildi', SiteSetting::get('whatsapp_message'));
    }

    public function test_clearing_the_number_hides_the_button(): void
    {
        Category::create(['name' => 'Cement', 'slug' => 'cement', 'icon' => 'cement']);
        SiteSetting::set('whatsapp_number', null);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('wa.me', escape: false);
    }

    public function test_the_floating_button_links_to_the_business_whatsapp_with_a_prefilled_message(): void
    {
        Category::create(['name' => 'Cement', 'slug' => 'cement', 'icon' => 'cement']);
        SiteSetting::set('whatsapp_number', '237670000000');
        SiteSetting::set('whatsapp_message', 'Hi Selbuildi, I have a question');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('https://wa.me/237670000000?text=Hi%20Selbuildi%2C%20I%20have%20a%20question', escape: false)
            ->assertSee('Chat with us on WhatsApp', escape: false);
    }

    public function test_the_button_works_without_a_prefilled_message(): void
    {
        Category::create(['name' => 'Cement', 'slug' => 'cement', 'icon' => 'cement']);
        SiteSetting::set('whatsapp_number', '237670000000');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="https://wa.me/237670000000"', escape: false);
    }
}
