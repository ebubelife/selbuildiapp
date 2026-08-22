<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('test-email'))
            ->assertOk()
            ->assertSee('Sent successfully');

        Mail::assertSentCount(1);
    }
}
