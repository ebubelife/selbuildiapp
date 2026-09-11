<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            // Nullable: an agent added directly by admin (no self-registration)
            // has no login and stays roster-only, same as before this column
            // existed. A self-registered agent always has one.
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('photo_path')->nullable()->after('document_path');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('photo_path');
        });
    }
};
