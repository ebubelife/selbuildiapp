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
        Schema::table('trust_score_events', function (Blueprint $table) {
            $table->enum('event_type', [
                'order_completed', 'on_time_payment', 'late_payment', 'payment_failed', 'dispute', 'cancellation', 'kyc_verified',
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trust_score_events', function (Blueprint $table) {
            $table->enum('event_type', [
                'order_completed', 'on_time_payment', 'late_payment', 'dispute', 'cancellation', 'kyc_verified',
            ])->change();
        });
    }
};
