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
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('project_country')->nullable()->after('country');
            $table->string('city')->nullable()->after('project_country');
            $table->enum('account_type', ['individual', 'diaspora_buyer', 'property_developer'])
                ->nullable()
                ->after('city');
            $table->string('preferred_currency', 3)->default('XAF')->after('account_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'project_country', 'city', 'account_type', 'preferred_currency',
            ]);
        });
    }
};
