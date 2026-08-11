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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'contractor', 'supplier', 'admin'])
                ->default('customer')
                ->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->string('country')->nullable()->after('phone');
            $table->boolean('is_diaspora')->default(false)->after('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'country', 'is_diaspora']);
        });
    }
};
