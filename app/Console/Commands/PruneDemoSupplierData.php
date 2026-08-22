<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierProfile;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

#[Signature('db:prune-demo-suppliers {--force : Skip the confirmation prompt}')]
#[Description('Remove the fake suppliers/products/warehouses created by SupplierSeeder and ProductSeeder')]
class PruneDemoSupplierData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::where('email', 'like', '%@selbuildi-suppliers.test')->get();

        if ($users->isEmpty()) {
            $this->info('No demo supplier data found - nothing to do.');

            return self::SUCCESS;
        }

        $this->table(
            ['Business', 'Email', 'Products'],
            $users->map(function (User $user) {
                $profile = $user->supplierProfile;
                $productCount = $profile ? Product::where('supplier_profile_id', $profile->id)->count() : 0;

                return [$profile?->business_name ?? '(no profile)', $user->email, $productCount];
            })
        );

        if (! $this->option('force') && ! $this->confirm('Delete these demo suppliers and everything under them?')) {
            $this->info('Cancelled - nothing was deleted.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $this->pruneOne($user);
        }

        return self::SUCCESS;
    }

    private function pruneOne(User $user): void
    {
        $profile = $user->supplierProfile;

        if (! $profile) {
            $user->delete();

            return;
        }

        try {
            DB::transaction(function () use ($user, $profile) {
                $products = Product::where('supplier_profile_id', $profile->id)->get();

                foreach ($products as $product) {
                    $product->inventories()->delete();
                    $product->delete();
                }

                Warehouse::where('supplier_profile_id', $profile->id)->delete();
                $profile->delete();
                $user->delete();
            });

            $this->info("Removed {$profile->business_name}.");
        } catch (QueryException $e) {
            // restrictOnDelete() on order_items.product_id/supplier_profile_id
            // means the database itself refuses this if there's a real order
            // against this supplier - surface that plainly instead of a raw
            // SQL error, and leave that supplier's data untouched.
            $this->error("Skipped {$profile->business_name}: it has real orders attached, so it wasn't deleted. ({$e->getMessage()})");
        }
    }
}
