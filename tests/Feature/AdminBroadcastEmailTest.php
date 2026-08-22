<?php

namespace Tests\Feature;

use App\Filament\Pages\SendEmail;
use App\Models\User;
use App\Notifications\AdminBroadcastEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBroadcastEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_admin_cannot_access_the_send_email_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(SendEmail::class)->assertForbidden();
    }

    public function test_a_super_admin_can_email_every_user(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $supplier = User::factory()->create(['role' => 'supplier']);

        $this->actingAs($superAdmin, 'admin');

        Livewire::test(SendEmail::class)
            ->fillForm([
                'recipients' => 'all',
                'subject' => 'Scheduled maintenance tonight',
                'body' => "We'll be briefly offline tonight for improvements.",
            ])
            ->call('send');

        Notification::assertSentTo($customer, AdminBroadcastEmail::class);
        Notification::assertSentTo($supplier, AdminBroadcastEmail::class);
        Notification::assertSentTo($superAdmin, AdminBroadcastEmail::class);
    }

    public function test_a_super_admin_can_email_one_specific_user(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $targetUser = User::factory()->create(['role' => 'customer']);
        $otherUser = User::factory()->create(['role' => 'customer']);

        $this->actingAs($superAdmin, 'admin');

        Livewire::test(SendEmail::class)
            ->fillForm([
                'recipients' => 'specific',
                'user_id' => $targetUser->id,
                'subject' => 'About your recent order',
                'body' => 'Following up on your order.',
            ])
            ->call('send');

        Notification::assertSentTo($targetUser, AdminBroadcastEmail::class);
        Notification::assertNotSentTo($otherUser, AdminBroadcastEmail::class);
    }
}
