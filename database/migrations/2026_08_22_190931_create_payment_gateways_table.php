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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('display_name');
            $table->boolean('is_enabled')->default(false);
            $table->enum('mode', ['test', 'live'])->default('test');
            // Encrypted at the model level (PaymentGateway casts this
            // 'encrypted:array') - API secrets never sit in the database
            // in plain text.
            $table->text('credentials')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
