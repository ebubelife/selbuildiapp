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
        // Admin-managed roster, not a User account - a delivery agent
        // doesn't log in or have a dashboard, they're just assigned to
        // shipments (see delivery_agent_id on shipments) so admin/ops can
        // see who's actually carrying a given order.
        Schema::create('delivery_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('phone_2')->nullable();
            $table->string('vehicle_id')->nullable();
            $table->string('document_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_agents');
    }
};
