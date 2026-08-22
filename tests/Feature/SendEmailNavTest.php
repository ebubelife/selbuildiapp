<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendEmailNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_send_email_in_the_sidebar(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin, 'admin')
            ->get('/s/admin/build')
            ->assertOk()
            ->assertSee('Send Email');
    }

    public function test_plain_admin_does_not_see_send_email_in_the_sidebar(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get('/s/admin/build')
            ->assertOk()
            ->assertDontSee('Send Email');
    }
}
