<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CreditAccount;
use App\Models\Order;
use App\Models\ProcurementTrustScore;
use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Services\CartService;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshDatabase;

    private function withTier(User $user, string $tier, int $score = 70): User
    {
        ProcurementTrustScore::create([
            'user_id' => $user->id,
            'score' => $score,
            'tier' => $tier,
            'calculated_at' => now(),
        ]);

        return $user;
    }

    private function createProduct(int $price = 9500): Product
    {
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Douala Building Depot',
            'slug' => 'douala-building-depot-'.uniqid(),
            'verified_at' => now(),
        ]);

        $category = Category::create([
            'name' => 'Roofing',
            'slug' => 'roofing-'.uniqid(),
            'icon' => 'roofing',
        ]);

        return Product::create([
            'supplier_profile_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Aluminium Roofing Sheet',
            'slug' => 'aluminium-roofing-sheet-'.uniqid(),
            'sku' => 'ROOF-'.uniqid(),
            'unit' => 'piece',
            'price' => $price,
            'min_order_qty' => 1,
            'is_active' => true,
            'is_featured' => false,
        ]);
    }

    public function test_an_unrated_user_cannot_apply_for_credit(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->assertFalse(app(CreditService::class)->canApply($user));
    }

    public function test_a_gold_tier_request_within_the_auto_approve_limit_is_approved_instantly(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'gold');

        $account = app(CreditService::class)->applyForCredit($user, 150000);

        $this->assertSame('approved', $account->status);
        $this->assertSame(150000, $account->credit_limit);
        $this->assertSame(150000, $account->available_credit);
        $this->assertNotNull($account->approved_at);
    }

    public function test_a_gold_tier_request_over_the_auto_approve_limit_is_pending(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'gold');

        $account = app(CreditService::class)->applyForCredit($user, 900000);

        $this->assertSame('pending', $account->status);
        $this->assertSame(0, $account->available_credit);
    }

    public function test_a_bronze_tier_request_always_requires_manual_review(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'bronze', score: 10);

        $account = app(CreditService::class)->applyForCredit($user, 10000);

        $this->assertSame('pending', $account->status);
    }

    public function test_credit_review_command_approves_a_pending_application(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'bronze', score: 10);
        app(CreditService::class)->applyForCredit($user, 10000);

        $exitCode = Artisan::call('credit:review', [
            'user' => $user->email,
            'decision' => 'approve',
            '--limit' => 20000,
        ]);

        $this->assertSame(0, $exitCode);

        $account = $user->creditAccount->fresh();
        $this->assertSame('approved', $account->status);
        $this->assertSame(20000, $account->credit_limit);
        $this->assertSame(20000, $account->available_credit);
    }

    public function test_credit_review_command_rejects_a_pending_application(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'bronze', score: 10);
        app(CreditService::class)->applyForCredit($user, 10000);

        Artisan::call('credit:review', ['user' => $user->email, 'decision' => 'reject']);

        $this->assertSame('rejected', $user->creditAccount->fresh()->status);
    }

    public function test_credit_review_command_refuses_to_re_review_a_non_pending_account(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'gold');
        app(CreditService::class)->applyForCredit($user, 150000); // auto-approved

        $exitCode = Artisan::call('credit:review', [
            'user' => $user->email,
            'decision' => 'approve',
            '--limit' => 999999,
        ]);

        $this->assertSame(1, $exitCode);
    }

    public function test_checkout_offers_selbuildi_credit_when_approved_and_sufficient(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'gold');
        CreditAccount::create([
            'user_id' => $user->id,
            'credit_limit' => 100000,
            'available_credit' => 100000,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($user);
        app(CartService::class)->add($this->createProduct(9500), 1);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->assertSee('Pay with Selbuildi Credit');
    }

    public function test_checkout_hides_selbuildi_credit_when_available_credit_is_insufficient(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'gold');
        CreditAccount::create([
            'user_id' => $user->id,
            'credit_limit' => 5000,
            'available_credit' => 5000,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($user);
        app(CartService::class)->add($this->createProduct(9500), 1);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->assertDontSee('Pay with Selbuildi Credit');
    }

    public function test_placing_an_order_with_credit_draws_down_the_account_and_creates_a_transaction(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'gold');
        $account = CreditAccount::create([
            'user_id' => $user->id,
            'credit_limit' => 100000,
            'available_credit' => 100000,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($user);
        app(CartService::class)->add($this->createProduct(9500), 1);

        $address = $user->addresses()->create([
            'recipient_name' => 'Test Customer',
            'phone' => '+237600000000',
            'country' => 'Cameroon',
            'city' => 'Douala',
            'street' => '123 Rue de la Paix',
            'is_default' => true,
        ]);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->set('selectedAddressId', $address->id)
            ->set('paymentMethod', 'selbuildi_credit')
            ->call('placeOrder');

        $order = Order::sole();
        $this->assertSame('selbuildi_credit', $order->payment_method);

        $this->assertSame(90500, $account->fresh()->available_credit);

        $this->assertDatabaseHas('credit_transactions', [
            'credit_account_id' => $account->id,
            'order_id' => $order->id,
            'type' => 'drawdown',
            'amount' => 9500,
            'status' => 'pending',
        ]);
    }

    public function test_repaying_a_drawdown_restores_available_credit_and_marks_it_paid(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'gold');
        $account = CreditAccount::create([
            'user_id' => $user->id,
            'credit_limit' => 100000,
            'available_credit' => 90500,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        $drawdown = $account->transactions()->create([
            'type' => 'drawdown',
            'amount' => 9500,
            'balance_after' => 90500,
            'due_date' => now()->addDays(15),
            'status' => 'pending',
        ]);

        app(CreditService::class)->repay($drawdown);

        $this->assertSame(100000, $account->fresh()->available_credit);
        $this->assertSame('paid', $drawdown->fresh()->status);
        $this->assertNotNull($drawdown->fresh()->paid_at);

        $this->assertDatabaseHas('trust_score_events', [
            'user_id' => $user->id,
            'event_type' => 'on_time_payment',
        ]);
    }

    public function test_repaying_a_drawdown_after_its_due_date_fires_a_late_payment_event(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'gold');
        $account = CreditAccount::create([
            'user_id' => $user->id,
            'credit_limit' => 100000,
            'available_credit' => 90500,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        $drawdown = $account->transactions()->create([
            'type' => 'drawdown',
            'amount' => 9500,
            'balance_after' => 90500,
            'due_date' => now()->subDays(3),
            'status' => 'pending',
        ]);

        app(CreditService::class)->repay($drawdown);

        $this->assertDatabaseHas('trust_score_events', [
            'user_id' => $user->id,
            'event_type' => 'late_payment',
        ]);
    }

    public function test_my_credit_page_shows_apply_form_for_bronze_tier(): void
    {
        $user = $this->withTier(User::factory()->create(['role' => 'customer']), 'bronze', score: 10);
        $this->actingAs($user);

        Volt::test('credit.index')->assertSee('Apply for Credit');
    }

    public function test_my_credit_page_is_forbidden_for_suppliers(): void
    {
        $user = User::factory()->create(['role' => 'supplier']);
        $this->actingAs($user);

        Volt::test('credit.index')->assertStatus(403);
    }
}
