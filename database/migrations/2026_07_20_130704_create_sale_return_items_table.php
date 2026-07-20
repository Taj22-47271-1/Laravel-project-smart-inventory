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
        Schema::create(
            'sale_return_items',
            function (Blueprint $table) {
                $table->id();

                /*
                 * যে sale return-এর অধীনে item থাকবে।
                 */
                $table->foreignId('sale_return_id')
                    ->constrained('sale_returns')
                    ->cascadeOnDelete();

                /*
                 * Original sale-এর যে item return করা হচ্ছে।
                 */
                $table->foreignId('sale_item_id')
                    ->constrained('sale_items')
                    ->restrictOnDelete();

                /*
                 * Return করা product।
                 */
                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                /*
                 * Return করা quantity।
                 */
                $table->decimal('quantity', 15, 3);

                /*
                 * Original sale-এর unit price।
                 */
                $table->decimal('unit_price', 15, 2);

                /*
                 * Return quantity অনুযায়ী discount অংশ।
                 */
                $table->decimal('discount_amount', 15, 2)
                    ->default(0);

                /*
                 * Return quantity অনুযায়ী tax অংশ।
                 */
                $table->decimal('tax_amount', 15, 2)
                    ->default(0);

                /*
                 * Customer-কে এই item-এর জন্য refund amount।
                 */
                $table->decimal('refund_amount', 15, 2);

                $table->timestamps();

                /*
                 * একই return-এ একই original sale item
                 * একাধিক row হিসেবে যোগ হওয়া বন্ধ করবে।
                 */
                $table->unique([
                    'sale_return_id',
                    'sale_item_id',
                ]);

                $table->index('product_id');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};