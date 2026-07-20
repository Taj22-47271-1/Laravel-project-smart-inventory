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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->string('sale_number', 50)->unique();

            /*
             * Customer optional রাখা হয়েছে,
             * যাতে walk-in customer-এর কাছেও sale করা যায়।
             */
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->date('sale_date');

            $table->decimal('subtotal', 15, 2)
                ->default(0);

            $table->decimal('discount_amount', 15, 2)
                ->default(0);

            $table->decimal('tax_amount', 15, 2)
                ->default(0);

            $table->decimal('shipping_amount', 15, 2)
                ->default(0);

            $table->decimal('grand_total', 15, 2)
                ->default(0);

            $table->decimal('paid_amount', 15, 2)
                ->default(0);

            $table->decimal('due_amount', 15, 2)
                ->default(0);

            /*
             * Possible statuses:
             * draft
             * completed
             * cancelled
             */
            $table->string('status', 30)
                ->default('draft');

            /*
             * Possible payment statuses:
             * unpaid
             * partial
             * paid
             */
            $table->string('payment_status', 30)
                ->default('unpaid');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('sale_date');
            $table->index('status');
            $table->index('payment_status');

            $table->index([
                'customer_id',
                'sale_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};