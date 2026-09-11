<?php

namespace Tests\Feature;

use App\Filament\Resources\SupportTickets\Pages\ManageSupportTickets;
use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketReplied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_away_from_the_support_form(): void
    {
        $this->get(route('support.create'))->assertRedirect(route('login'));
    }

    public function test_a_customer_can_submit_a_general_enquiry(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        Volt::test('support.create')
            ->set('type', 'procurement_request')
            ->set('subject', 'Need help sourcing')
            ->set('body', '200 bags of 42.5R cement delivered to Douala.')
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $user->id,
            'type' => 'procurement_request',
            'subject' => 'Need help sourcing',
            'status' => 'new',
        ]);
    }

    public function test_requesting_a_quote_from_a_product_page_pre_fills_the_form(): void
    {
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Test Supplier',
            'slug' => 'test-supplier',
            'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Cement', 'slug' => 'cement', 'icon' => 'cement']);
        $product = Product::create([
            'supplier_profile_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Dangote Cement 50kg',
            'slug' => 'dangote-cement-50kg',
            'sku' => 'DANGOTE-50KG',
            'unit' => 'bag',
            'price' => 4800,
            'min_order_qty' => 1,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        // Full HTTP visit (not Volt::test(), which doesn't route mount()
        // through a real query string) confirms mount() actually reads
        // ?product= and pre-fills the quote-request copy/state.
        $this->get(route('support.create', ['product' => $product->id]))
            ->assertOk()
            ->assertSee('Request a Quote')
            ->assertSee($product->name);

        // Submission itself is exercised directly against the component,
        // with the product-bound state it would have after that mount().
        Volt::test('support.create')
            ->set('productId', $product->id)
            ->set('type', 'quote_request')
            ->set('subject', "Quote request: {$product->name}")
            ->set('body', 'Need 500 bags, delivered weekly.')
            ->call('submit');

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $user->id,
            'type' => 'quote_request',
            'product_id' => $product->id,
        ]);
    }

    public function test_an_admin_can_reply_to_a_ticket_and_the_customer_is_notified(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = SupportTicket::create([
            'user_id' => $customer->id,
            'type' => 'general',
            'subject' => 'Question about delivery',
            'body' => 'How long does delivery take to Bamenda?',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageSupportTickets::class)
            ->callTableAction('reply', $ticket, data: [
                'response' => 'Delivery to Bamenda usually takes 3-5 business days.',
            ]);

        $ticket->refresh();
        $this->assertSame('responded', $ticket->status);
        $this->assertNotNull($ticket->responded_at);
        $this->assertSame('Delivery to Bamenda usually takes 3-5 business days.', $ticket->response);

        Notification::assertSentTo($customer, SupportTicketReplied::class);
    }

    public function test_an_admin_can_assign_a_ticket_which_also_moves_it_to_in_progress(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teamMember = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = SupportTicket::create([
            'user_id' => $customer->id,
            'type' => 'general',
            'subject' => 'Question',
            'body' => 'Body text.',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageSupportTickets::class)
            ->callTableAction('assign', $ticket, data: ['assigned_to' => $teamMember->id]);

        $ticket->refresh();
        $this->assertSame($teamMember->id, $ticket->assigned_to);
        $this->assertSame('in_progress', $ticket->status);
    }

    public function test_an_admin_can_mark_a_ticket_resolved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = SupportTicket::create([
            'user_id' => $customer->id,
            'type' => 'general',
            'subject' => 'Question',
            'body' => 'Body text.',
            'status' => 'responded',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageSupportTickets::class)
            ->callTableAction('markResolved', $ticket);

        $this->assertSame('resolved', $ticket->fresh()->status);
    }

    public function test_the_navigation_badge_counts_only_new_tickets(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        SupportTicket::create(['user_id' => $customer->id, 'type' => 'general', 'subject' => 'A', 'body' => 'A', 'status' => 'new']);
        SupportTicket::create(['user_id' => $customer->id, 'type' => 'general', 'subject' => 'B', 'body' => 'B', 'status' => 'new']);
        SupportTicket::create(['user_id' => $customer->id, 'type' => 'general', 'subject' => 'C', 'body' => 'C', 'status' => 'resolved']);

        $this->assertSame('2', \App\Filament\Resources\SupportTickets\SupportTicketResource::getNavigationBadge());
    }

    public function test_a_supplier_can_submit_a_message_to_admin(): void
    {
        $user = User::factory()->create(['role' => 'supplier']);
        SupplierProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Test Supplier',
            'slug' => 'test-supplier-'.uniqid(),
            'verified_at' => now(),
        ]);
        $this->actingAs($user);

        Volt::test('support.create')
            ->assertSee('Contact Selbuildi')
            ->set('subject', 'Question about my listing')
            ->set('body', 'One of my products is showing the wrong price.')
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $user->id,
            'type' => 'general',
            'subject' => 'Question about my listing',
        ]);
    }

    public function test_a_supplier_does_not_see_the_sourcing_help_option(): void
    {
        $user = User::factory()->create(['role' => 'supplier']);
        SupplierProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Test Supplier',
            'slug' => 'test-supplier-'.uniqid(),
            'verified_at' => now(),
        ]);
        $this->actingAs($user);

        Volt::test('support.create')
            ->assertDontSee("Can't find what I need");
    }

    public function test_the_admin_ticket_list_shows_the_senders_account_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplierUser = User::factory()->create(['role' => 'supplier', 'name' => 'Acme Supplies']);
        SupportTicket::create(['user_id' => $supplierUser->id, 'type' => 'general', 'subject' => 'Listing issue', 'body' => 'Body text.']);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageSupportTickets::class)
            ->assertSee('Acme Supplies')
            ->assertSee('Supplier');
    }

    public function test_admin_can_filter_tickets_by_account_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'A Customer']);
        $supplierUser = User::factory()->create(['role' => 'supplier', 'name' => 'A Supplier']);
        SupportTicket::create(['user_id' => $customer->id, 'type' => 'general', 'subject' => 'From customer', 'body' => 'Body.']);
        SupportTicket::create(['user_id' => $supplierUser->id, 'type' => 'general', 'subject' => 'From supplier', 'body' => 'Body.']);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageSupportTickets::class)
            ->assertSee('From customer')
            ->assertSee('From supplier')
            ->filterTable('account_type', 'supplier')
            ->assertSee('From supplier')
            ->assertDontSee('From customer');
    }
}
