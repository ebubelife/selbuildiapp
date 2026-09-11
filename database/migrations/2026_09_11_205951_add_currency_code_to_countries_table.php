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
        // Lets a logged-in customer's registered country auto-select a
        // sensible starting display/charge currency (e.g. Nigeria ->
        // NGN) without guessing from an IP address - nullable, since most
        // of the 195 seeded countries don't need a currency mapped
        // unless/until an admin actually wants to support their currency.
        Schema::table('countries', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('checkout_enabled');
        });

        DB::table('countries')->where('code', 'CM')->update(['currency_code' => 'XAF']);
        DB::table('countries')->where('code', 'NG')->update(['currency_code' => 'NGN']);
        DB::table('countries')->where('code', 'GH')->update(['currency_code' => 'GHS']);
        DB::table('countries')->where('code', 'ZA')->update(['currency_code' => 'ZAR']);
        DB::table('countries')->where('code', 'KE')->update(['currency_code' => 'KES']);
        DB::table('countries')->where('code', 'US')->update(['currency_code' => 'USD']);
        DB::table('countries')->where('code', 'GB')->update(['currency_code' => 'GBP']);
        DB::table('countries')->whereIn('code', ['FR', 'DE', 'ES', 'IT', 'BE', 'NL', 'PT', 'IE'])->update(['currency_code' => 'EUR']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('currency_code');
        });
    }
};
