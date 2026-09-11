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
        // One currency, not one per country - several countries share a
        // currency (all of CEMAC uses XAF), and maintaining the same rate
        // in multiple places would let them silently drift apart.
        // rate_to_xaf = how many XAF equal 1 unit of this currency (e.g.
        // USD ~605, NGN ~0.42) - matches how people actually state
        // exchange rates locally ("1 dollar is about 600 francs"), and is
        // what checkout multiplies/divides against to charge in this
        // currency while keeping XAF as the canonical ledger amount.
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 8);
            $table->string('country_label')->nullable();
            $table->decimal('rate_to_xaf', 14, 6);
            $table->enum('rate_source', ['manual', 'live'])->default('manual');
            $table->timestamp('rate_fetched_at')->nullable();
            $table->boolean('is_supported')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('currencies')->insert([
            'code' => 'XAF',
            'name' => 'Central African CFA Franc',
            'symbol' => 'XAF',
            'country_label' => 'Cameroon',
            'rate_to_xaf' => 1,
            'rate_source' => 'manual',
            'is_supported' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
