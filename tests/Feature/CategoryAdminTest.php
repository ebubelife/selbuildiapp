<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCategories::class)
            ->callAction('create', data: [
                'name' => 'Insulation',
                'slug' => 'insulation',
                'sort_order' => 5,
            ]);

        $this->assertDatabaseHas('categories', ['name' => 'Insulation', 'slug' => 'insulation']);
    }

    public function test_an_admin_can_edit_a_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Cement', 'slug' => 'cement']);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCategories::class)
            ->callTableAction('edit', $category, data: [
                'name' => 'Cement & Concrete',
                'slug' => 'cement',
                'sort_order' => 1,
            ]);

        $this->assertSame('Cement & Concrete', $category->fresh()->name);
    }

    public function test_a_category_can_have_a_parent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $parent = Category::create(['name' => 'Building Materials', 'slug' => 'building-materials']);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageCategories::class)
            ->callAction('create', data: [
                'name' => 'Cement',
                'slug' => 'cement',
                'parent_id' => $parent->id,
            ]);

        $this->assertDatabaseHas('categories', ['name' => 'Cement', 'parent_id' => $parent->id]);
    }
}
