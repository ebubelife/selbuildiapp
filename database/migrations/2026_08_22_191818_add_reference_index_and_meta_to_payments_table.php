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
        Schema::table('payments', function (Blueprint $table) {
            // Unique so a webhook/callback can never be matched against
            // more than one payment row - MySQL and SQLite both allow
            // multiple NULLs in a unique index, so existing null
            // references (none yet - online gateways are new) aren't a
            // conflict.
            $table->unique('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['reference']);
        });
    }
};
