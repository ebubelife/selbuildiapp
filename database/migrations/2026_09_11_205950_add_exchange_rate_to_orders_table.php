<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Snapshot of currencies.rate_to_xaf at the moment this order
            // was placed. subtotal/shipping_fee/tax/discount/total stay
            // in XAF always (the canonical ledger amount, unaffected by
            // what currency the customer actually pays in) - this is
            // purely what lets us recompute the exact amount charged
            // (currency column x this rate) without the number silently
            // shifting if an admin edits the live rate later, and without
            // needing to touch any existing XAF-based reporting/sums.
            $table->decimal('exchange_rate', 14, 6)->default(1)->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
