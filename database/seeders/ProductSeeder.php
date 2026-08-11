<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = SupplierProfile::all();

        if ($suppliers->isEmpty()) {
            return;
        }

        $products = [
            'Cement' => [
                ['name' => 'Dangote Cement 50kg', 'unit' => 'bag', 'price' => 4800],
                ['name' => 'Cimencam Cement 50kg', 'unit' => 'bag', 'price' => 4700],
            ],
            'Steel & Rebar' => [
                ['name' => 'Reinforcement Steel Y12', 'unit' => 'piece', 'price' => 6200],
                ['name' => 'Reinforcement Steel Y16', 'unit' => 'piece', 'price' => 8900],
            ],
            'Roofing' => [
                ['name' => 'Aluminium Roofing Sheet', 'unit' => 'piece', 'price' => 9500],
                ['name' => 'Zinc Roofing Sheet 3m', 'unit' => 'piece', 'price' => 7200],
            ],
            'Tiles' => [
                ['name' => 'Ceramic Floor Tile 60x60', 'unit' => 'meter', 'price' => 3100],
                ['name' => 'Porcelain Wall Tile 30x60', 'unit' => 'meter', 'price' => 4200],
            ],
            'Blocks' => [
                ['name' => 'Concrete Block 9-inch', 'unit' => 'piece', 'price' => 450],
                ['name' => 'Concrete Block 6-inch', 'unit' => 'piece', 'price' => 350],
            ],
            'Tools' => [
                ['name' => 'Wheelbarrow Heavy Duty', 'unit' => 'piece', 'price' => 25000],
                ['name' => 'Spirit Level 60cm', 'unit' => 'piece', 'price' => 6500],
            ],
        ];

        $supplierIndex = 0;

        foreach ($products as $categoryName => $items) {
            $category = Category::where('name', $categoryName)->first();

            if (! $category) {
                continue;
            }

            foreach ($items as $item) {
                $supplier = $suppliers[$supplierIndex % $suppliers->count()];
                $supplierIndex++;

                $product = Product::updateOrCreate(
                    ['sku' => 'SB-'.str($item['name'])->slug()],
                    [
                        'supplier_profile_id' => $supplier->id,
                        'category_id' => $category->id,
                        'name' => $item['name'],
                        'slug' => str($item['name'])->slug().'-'.Str::lower(Str::random(4)),
                        'description' => "Quality {$item['name']} sourced and verified through Selbuildi's supplier network.",
                        'unit' => $item['unit'],
                        'price' => $item['price'],
                        'min_order_qty' => 1,
                        'is_active' => true,
                        'is_featured' => true,
                    ]
                );

                $warehouse = $supplier->warehouses()->first();

                if ($warehouse) {
                    $product->inventories()->firstOrCreate(
                        ['warehouse_id' => $warehouse->id],
                        ['quantity_available' => fake()->numberBetween(50, 500)]
                    );
                }
            }
        }
    }
}
