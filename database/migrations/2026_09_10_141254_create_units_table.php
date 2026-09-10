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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seeded with exactly the six values that were hardcoded in the
        // products.unit enum, so this is a pure refactor - existing
        // products keep working unchanged.
        $units = ['bag', 'ton', 'piece', 'meter', 'liter', 'roll'];

        DB::table('units')->insert(
            collect($units)->map(fn (string $name, int $i) => [
                'name' => $name,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );

        // Lift the DB-level enum constraint so admin-created units can
        // actually be stored (same approach as the role / trust-event
        // enum migrations already in this project).
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('unit', ['bag', 'ton', 'piece', 'meter', 'liter', 'roll'])->change();
        });

        Schema::dropIfExists('units');
    }
};
