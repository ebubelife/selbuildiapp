<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Units\Pages\ManageUnits;
use App\Models\Category;
use App\Models\SupplierProfile;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UnitAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_six_original_units_are_seeded(): void
    {
        $this->assertSame(
            ['bag', 'ton', 'piece', 'meter', 'liter', 'roll'],
            Unit::orderBy('sort_order')->pluck('name')->all()
        );
    }

    public function test_an_admin_can_create_a_new_unit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManageUnits::class)
            ->callAction('create', data: ['name' => 'Sheet', 'sort_order' => 10]);

        // Name is lowercased on the way in.
        $this->assertDatabaseHas('units', ['name' => 'sheet', 'sort_order' => 10]);
    }

    public function test_an_admin_can_delete_a_unit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $roll = Unit::where('name', 'roll')->sole();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageUnits::class)
            ->callTableAction('delete', $roll);

        $this->assertDatabaseMissing('units', ['name' => 'roll']);
    }

    public function test_a_newly_created_unit_can_be_selected_when_creating_a_product(): void
    {
        Storage::fake('public');

        Unit::create(['name' => 'pallet', 'sort_order' => 20]);

        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = SupplierProfile::create([
            'user_id' => User::factory()->create(['role' => 'supplier'])->id,
            'business_name' => 'Test Supplier',
            'slug' => 'test-supplier-'.uniqid(),
            'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Cement', 'slug' => 'cement-'.uniqid(), 'icon' => 'cement']);

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'supplier_profile_id' => $supplier->id,
                'name' => 'Palletised Block',
                'category_id' => $category->id,
                'unit' => 'pallet',
                'price' => 12000,
                'min_order_qty' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['name' => 'Palletised Block', 'unit' => 'pallet']);
    }

    public function test_a_supplier_product_form_rejects_a_unit_that_does_not_exist(): void
    {
        $user = User::factory()->create(['role' => 'supplier']);
        SupplierProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Verified Supplier',
            'slug' => 'verified-supplier-'.uniqid(),
            'verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Cement', 'slug' => 'cement-'.uniqid(), 'icon' => 'cement']);

        $this->actingAs($user);

        Volt::test('supplier.products.form')
            ->set('name', 'Bad Unit Product')
            ->set('category_id', $category->id)
            ->set('unit', 'not-a-real-unit')
            ->set('price', 4500)
            ->set('min_order_qty', 1)
            ->set('quantity_available', 10)
            ->call('save')
            ->assertHasErrors('unit');
    }
}
