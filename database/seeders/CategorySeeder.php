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
            ['name' => 'Cement', 'icon' => 'cement'],
            ['name' => 'Steel & Rebar', 'icon' => 'steel'],
            ['name' => 'Roofing', 'icon' => 'roofing'],
            ['name' => 'Tiles', 'icon' => 'tiles'],
            ['name' => 'Blocks', 'icon' => 'blocks'],
            ['name' => 'Tools', 'icon' => 'tools'],
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
