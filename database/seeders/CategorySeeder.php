<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Existing categories keep their current names/slugs as-is -
            // renaming them here would change their slug and make
            // updateOrCreate() below insert a new row instead of updating
            // the existing one, orphaning any products already linked to
            // the old category id. Renames/merges (e.g. "Cement" +
            // "Blocks") are a deliberate follow-up once the final list is
            // agreed, done via the admin panel so products get reassigned.
            ['name' => 'Cement', 'icon' => 'cement'],
            ['name' => 'Steel & Rebar', 'icon' => 'steel'],
            ['name' => 'Roofing', 'icon' => 'roofing'],
            ['name' => 'Tiles', 'icon' => 'tiles'],
            ['name' => 'Blocks', 'icon' => 'blocks'],
            ['name' => 'Tools', 'icon' => 'tools'],

            // New categories from the Aug 2026 site review - no dedicated
            // icon yet, so they fall back to the generic icon (same as any
            // other category without one) until custom ones are drawn.
            ['name' => 'Sand & Granite', 'icon' => null],
            ['name' => 'Timber & Boards', 'icon' => null],
            ['name' => 'Plumbing', 'icon' => null],
            ['name' => 'Electrical', 'icon' => null],
            ['name' => 'Doors & Windows', 'icon' => null],
            ['name' => 'Paints & Finishing', 'icon' => null],
            ['name' => 'Adhesives & Supplies', 'icon' => null],
        ];

        foreach ($categories as $index => $category) {
            Category::updateOrCreate(
                ['slug' => str($category['name'])->slug()],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'sort_order' => $index,
                ]
            );
        }
    }
}
