<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Project;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_a_non_contractor_cannot_access_the_projects_index(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        Volt::test('projects.index')->assertStatus(403);
    }

    public function test_a_contractor_can_create_a_project(): void
    {
        $user = User::factory()->create(['role' => 'contractor']);
        $this->actingAs($user);

        Volt::test('projects.index')
            ->set('name', 'Bafoussam Family House')
            ->set('budget', 5000000)
            ->call('createProject');

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'name' => 'Bafoussam Family House',
            'budget' => 5000000,
        ]);
    }

    public function test_project_name_is_required(): void
    {
        $user = User::factory()->create(['role' => 'contractor']);
        $this->actingAs($user);

        Volt::test('projects.index')
            ->set('name', '')
            ->call('createProject')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_projects_index_only_shows_the_contractors_own_projects(): void
    {
        $owner = User::factory()->create(['role' => 'contractor']);
        $owner->projects()->create(['name' => 'My House']);

        $other = User::factory()->create(['role' => 'contractor']);
        $other->projects()->create(['name' => 'Someone Elses House']);

        $this->actingAs($owner);

        Volt::test('projects.index')
            ->assertSee('My House')
            ->assertDontSee('Someone Elses House');
    }

    public function test_a_user_cannot_view_another_contractors_project(): void
    {
        $owner = User::factory()->create(['role' => 'contractor']);
        $project = $owner->projects()->create(['name' => 'My House']);

        $intruder = User::factory()->create(['role' => 'contractor']);
        $this->actingAs($intruder);

        Volt::test('projects.show', ['project' => $project])->assertStatus(403);
    }

    public function test_project_show_page_reports_spend_against_budget(): void
    {
        $owner = User::factory()->create(['role' => 'contractor']);
        $project = $owner->projects()->create(['name' => 'My House', 'budget' => 10000]);

        $owner->orders()->create([
            'order_number' => 'SB-TEST-0001',
            'status' => 'pending',
            'subtotal' => 4000,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 4000,
            'currency' => 'XAF',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'project_id' => $project->id,
            'placed_at' => now(),
        ]);

        $this->actingAs($owner);

        Volt::test('projects.show', ['project' => $project])
            ->assertViewHas('spent', 4000)
            ->assertViewHas('remaining', 6000);
    }

    public function test_checkout_offers_a_project_selector_for_contractors_with_active_projects(): void
    {
        $user = User::factory()->create(['role' => 'contractor']);
        $project = $user->projects()->create(['name' => 'My House', 'status' => 'active']);

        $this->actingAs($user);
        app(CartService::class)->add($this->createProduct(), 1);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->assertSee('My House');
    }

    public function test_checkout_hides_project_selector_for_customers(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user);
        app(CartService::class)->add($this->createProduct(), 1);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->assertDontSee('Link to a project');
    }

    public function test_placing_an_order_with_a_selected_project_links_it(): void
    {
        $user = User::factory()->create(['role' => 'contractor']);
        $project = $user->projects()->create(['name' => 'My House', 'status' => 'active']);

        $this->actingAs($user);
        app(CartService::class)->add($this->createProduct(), 1);

        $address = $user->addresses()->create([
            'recipient_name' => 'Test Contractor',
            'phone' => '+237600000000',
            'country' => 'Cameroon',
            'city' => 'Douala',
            'street' => '123 Rue de la Paix',
            'is_default' => true,
        ]);

        Volt::test('checkout.index')
            ->set('step', 'confirm')
            ->set('selectedAddressId', $address->id)
            ->set('selectedProjectId', $project->id)
            ->call('placeOrder');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);
    }
}
