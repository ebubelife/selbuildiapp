<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            // Anyone can browse/order from anywhere - this only controls
            // which countries a customer can pick as a *delivery* address
            // at checkout, since Selbuildi doesn't ship building materials
            // everywhere. Defaults to off; an admin turns specific
            // countries on from Settings -> Countries.
            $table->boolean('checkout_enabled')->default(false)->after('code');
        });

        // Cameroon has been the only country ever actually usable at
        // checkout (it was a hardcoded default everywhere) - carry that
        // forward as the one enabled country rather than silently
        // breaking checkout for every existing customer.
        DB::table('countries')->where('code', 'CM')->update(['checkout_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('checkout_enabled');
        });
    }
};
