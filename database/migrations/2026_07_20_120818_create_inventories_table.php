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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('quantity', 15, 3)
                ->default(0);

            $table->decimal('reserved_quantity', 15, 3)
                ->default(0);

            $table->decimal('average_cost', 15, 2)
                ->default(0);

            $table->timestamps();

            $table->index('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};