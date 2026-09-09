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
        // One row per tier (excluding 'unrated', which has no perk) - a
        // single source of truth that both the homepage's "Trust You Can
        // Build" section and CreditService's actual approval logic read
        // from, so what's advertised can never drift from what's enforced.
        Schema::create('credit_tier_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tier')->unique();
            $table->string('perk_headline');
            $table->unsignedInteger('auto_approve_limit')->nullable();
            $table->unsignedSmallInteger('net_terms_days')->nullable();
            $table->unsignedTinyInteger('deposit_percentage')->nullable();
            $table->timestamps();
        });

        // Seeded with exactly today's hardcoded values (see
        // CreditService::AUTO_APPROVE_LIMITS / NET_TERMS_DAYS) so this
        // migration is a pure refactor - no behavior changes until an
        // admin actually edits a row.
        DB::table('credit_tier_settings')->insert([
            ['tier' => 'bronze', 'perk_headline' => 'Eligible to apply for credit', 'auto_approve_limit' => null, 'net_terms_days' => null, 'deposit_percentage' => null, 'created_at' => now(), 'updated_at' => now()],
            ['tier' => 'silver', 'perk_headline' => '30% deposit, balance on delivery', 'auto_approve_limit' => null, 'net_terms_days' => null, 'deposit_percentage' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['tier' => 'gold', 'perk_headline' => 'Net-15 credit terms', 'auto_approve_limit' => 200000, 'net_terms_days' => 15, 'deposit_percentage' => null, 'created_at' => now(), 'updated_at' => now()],
            ['tier' => 'platinum', 'perk_headline' => 'Net-30, higher limits', 'auto_approve_limit' => 500000, 'net_terms_days' => 30, 'deposit_percentage' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_tier_settings');
    }
};
