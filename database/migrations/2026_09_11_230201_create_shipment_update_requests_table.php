<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A delivery agent never touches the live shipment/order directly -
        // every status change they submit lands here first, as a pending
        // request, until an admin reviews and applies (or rejects) it.
        Schema::create('shipment_update_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_agent_id')->constrained()->cascadeOnDelete();
            $table->string('requested_status');
            $table->text('note')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_update_requests');
    }
};
