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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 'quote_request' is optionally tied to an existing catalog
            // product; 'procurement_request' ("Can't Find What You Need")
            // is open-ended sourcing, so product_id stays null for it -
            // the material/quantity/location details live in body/subject
            // rather than their own columns, since only one of the five
            // types actually needs them.
            $table->enum('type', ['general', 'procurement_request', 'quote_request', 'complaint', 'other'])->default('general');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->enum('status', ['new', 'in_progress', 'responded', 'resolved'])->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
