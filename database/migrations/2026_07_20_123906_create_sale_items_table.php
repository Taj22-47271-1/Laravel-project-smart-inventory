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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('quantity', 15, 3);

            /*
             * ভবিষ্যতে sale return করলে কত quantity
             * ফেরত এসেছে, সেটি এখানে সংরক্ষিত হবে।
             */
            $table->decimal('returned_quantity', 15, 3)
                ->default(0);

            $table->decimal('unit_price', 15, 2);

            $table->decimal('discount_amount', 15, 2)
                ->default(0);

            $table->decimal('tax_amount', 15, 2)
                ->default(0);

            $table->decimal('line_total', 15, 2);

            $table->timestamps();

            /*
             * একই sale-এ একই product duplicate row
             * হিসেবে যোগ হওয়া বন্ধ করবে।
             */
            $table->unique([
                'sale_id',
                'product_id',
            ]);

            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
