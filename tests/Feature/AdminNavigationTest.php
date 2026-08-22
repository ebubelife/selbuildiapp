<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test: the "Logs" nav item's URL closure used to be
     * url(config('log-viewer.route_path')) with no fallback - if that
     * config key is ever missing (e.g. a stale/incomplete deploy that
     * hasn't picked up config/log-viewer.php yet), url(null) returns the
     * UrlGenerator instance itself rather than a string, which crashes
     * Filament's NavigationItem::getUrl() with a TypeError on every admin
     * page. This confirms the panel survives that exact scenario.
     */
    public function test_the_admin_panel_still_loads_if_the_log_viewer_config_is_missing(): void
    {
        Config::offsetUnset('log-viewer.route_path');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get('/s/admin/build')
            ->assertOk();
    }
}
