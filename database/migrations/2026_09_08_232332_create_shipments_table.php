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
        // One row per supplier-portion of an order - a multi-supplier order
        // gets one shipment per distinct supplier, since each fulfills and
        // ships their own items independently. `status` deliberately
        // mirrors the existing Order::STATUSES/OrderItem.fulfillment_status
        // vocabulary (a plain string, not a DB enum) rather than
        // introducing a second parallel status set.
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('carrier')->nullable();
            $table->string('tracking_reference')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('expected_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('proof_of_delivery_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'supplier_profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
