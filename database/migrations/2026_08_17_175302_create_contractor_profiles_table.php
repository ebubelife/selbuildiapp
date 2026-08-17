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
        Schema::create('contractor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('business_name')->nullable();
            $table->string('business_address')->nullable();
            $table->string('specialization')->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->string('registration_no')->nullable();
            $table->string('license_no')->nullable();
            // Stored on the private "local" disk, never "public" - these are
            // KYC documents, not something to serve at a public URL.
            $table->string('id_document_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contractor_profiles');
    }
};
