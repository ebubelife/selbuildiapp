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
        // Laravel 11+'s native column-alter system handles enum changes on
        // both MySQL and SQLite (the test suite's driver) without needing
        // doctrine/dbal, so ->change() works directly here.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'contractor', 'supplier', 'admin', 'super_admin'])
                ->default('customer')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'contractor', 'supplier', 'admin'])
                ->default('customer')
                ->change();
        });
    }
};
