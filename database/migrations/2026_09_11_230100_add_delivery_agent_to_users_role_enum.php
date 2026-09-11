<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'contractor', 'supplier', 'admin', 'super_admin', 'delivery_agent'])
                ->default('customer')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'delivery_agent')->update(['role' => 'customer']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'contractor', 'supplier', 'admin', 'super_admin'])
                ->default('customer')
                ->change();
        });
    }
};
