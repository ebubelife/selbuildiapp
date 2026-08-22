<?php

namespace Tests\Feature;

use App\Filament\Pages\ViewLogs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class ViewLogsTest extends TestCase
{
    use RefreshDatabase;

    private string $testLogPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testLogPath = storage_path('logs/laravel-test-fixture.log');
    }

    protected function tearDown(): void
    {
        File::delete($this->testLogPath);

        parent::tearDown();
    }

    public function test_a_guest_cannot_access_the_logs_page(): void
    {
        $this->get('/s/admin/build/view-logs')->assertRedirect();
    }

    public function test_a_customer_on_the_storefront_guard_cannot_access_the_logs_page(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get('/s/admin/build/view-logs')
            ->assertRedirect();
    }

    public function test_an_admin_can_access_the_logs_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get('/s/admin/build/view-logs')
            ->assertOk();
    }

    public function test_it_parses_the_most_recent_entries_newest_first(): void
    {
        File::put($this->testLogPath, implode("\n", [
            '[2026-08-22 10:00:00] production.INFO: First entry',
            'some stack trace line',
            '[2026-08-22 10:05:00] production.ERROR: Second entry',
            'another trace line',
        ]));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        $component = Livewire::test(ViewLogs::class)
            ->set('selectedFile', 'laravel-test-fixture.log');

        $entries = $component->instance()->entries();

        $this->assertCount(2, $entries);
        $this->assertSame('ERROR', $entries[0]['level']);
        $this->assertStringContainsString('Second entry', $entries[0]['summary']);
        $this->assertSame('INFO', $entries[1]['level']);
        $this->assertStringContainsString('First entry', $entries[1]['summary']);
    }

    public function test_it_lists_available_log_files(): void
    {
        File::put($this->testLogPath, '[2026-08-22 10:00:00] production.INFO: Test');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ViewLogs::class)
            ->assertSeeHtml('laravel-test-fixture.log');
    }
}
