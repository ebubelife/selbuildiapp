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
        Schema::table('products', function (Blueprint $table) {
            // Free-text for now (e.g. "12mm, Grade 60, 12m length") rather
            // than structured size/material/grade columns - faster to ship
            // and covers Sir George's examples; can be split into
            // structured fields later if faceted filtering by spec is
            // needed.
            $table->text('specification')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('specification');
        });
    }
};
