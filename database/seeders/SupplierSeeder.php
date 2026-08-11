<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            ['business_name' => 'Douala Building Depot', 'city' => 'Douala'],
            ['business_name' => 'Yaoundé Materials Co.', 'city' => 'Yaoundé'],
            ['business_name' => 'Bafoussam Hardware Hub', 'city' => 'Bafoussam'],
            ['business_name' => 'Limbe Steel & Supplies', 'city' => 'Limbe'],
        ];

        foreach ($suppliers as $supplier) {
            $user = User::firstOrCreate(
                ['email' => str($supplier['business_name'])->slug().'@selbuildi-suppliers.test'],
                [
                    'name' => $supplier['business_name'].' Admin',
                    'role' => 'supplier',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );

            $profile = $user->supplierProfile()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $supplier['business_name'],
                    'slug' => str($supplier['business_name'])->slug().'-'.Str::lower(Str::random(4)),
                    'description' => "Verified supplier of quality building materials based in {$supplier['city']}, Cameroon.",
                    'verified_at' => now(),
                ]
            );

            Warehouse::firstOrCreate(
                ['supplier_profile_id' => $profile->id, 'name' => $supplier['business_name'].' Warehouse'],
                ['supports_pickup' => true]
            );
        }
    }
}
