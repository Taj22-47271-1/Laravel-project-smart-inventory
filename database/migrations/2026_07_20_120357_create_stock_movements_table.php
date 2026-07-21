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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * Possible movement types:
             * purchase
             * sale
             * purchase_return
             * sale_return
             * adjustment_in
             * adjustment_out
             */
            $table->string('movement_type', 50);

            /*
             * Quantity সবসময় positive থাকবে।
             * movement_type অনুযায়ী stock বাড়বে বা কমবে।
             */
            $table->decimal('quantity', 15, 3);

            $table->decimal('stock_before', 15, 3)
                ->default(0);

            $table->decimal('stock_after', 15, 3)
                ->default(0);

            $table->decimal('unit_cost', 15, 2)
                ->nullable();

            /*
             * কোন purchase, sale বা অন্য record থেকে
             * stock movement হয়েছে সেটি সংরক্ষণ করবে।
             */
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('movement_type');

            $table->index([
                'product_id',
                'created_at',
            ]);

            $table->index([
                'reference_type',
                'reference_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
