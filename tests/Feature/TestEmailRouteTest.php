<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestEmailRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_trigger_the_test_email_route(): void
    {
        $this->get(route('test-email'))->assertRedirect();
    }

    public function test_an_admin_can_trigger_a_successful_test_email(): void
    {
        // Test env's MAIL_MAILER is 'array' (phpunit.xml) - genuinely safe
        // to let this actually run through the mailer rather than faking
        // it, since 'array' never touches the network.
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('test-email'))
            ->assertOk()
            ->assertSee('Sent successfully');
    }
}
