<?php

namespace Tests\Feature;

use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrandAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_brand(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'admin');

        Livewire::test(ManageBrands::class)
            ->callAction('create', data: [
                'name' => 'Dangote',
                'slug' => 'dangote',
            ]);

        $this->assertDatabaseHas('brands', ['name' => 'Dangote', 'slug' => 'dangote']);
    }

    public function test_an_admin_can_edit_a_brand(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $brand = Brand::create(['name' => 'Cimencam', 'slug' => 'cimencam']);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageBrands::class)
            ->callTableAction('edit', $brand, data: [
                'name' => 'CIMENCAM',
                'slug' => 'cimencam',
            ]);

        $this->assertSame('CIMENCAM', $brand->fresh()->name);
    }
}
