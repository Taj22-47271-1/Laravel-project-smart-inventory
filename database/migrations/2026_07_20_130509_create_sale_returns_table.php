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
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();

            $table->string('return_number', 50)
                ->unique();

            /*
             * যে completed sale থেকে product return হচ্ছে।
             */
            $table->foreignId('sale_id')
                ->constrained()
                ->restrictOnDelete();

            $table->date('return_date');

            /*
             * Return করা product-এর মূল মূল্য।
             */
            $table->decimal('subtotal', 15, 2)
                ->default(0);

            /*
             * Original sale discount-এর return অংশ।
             */
            $table->decimal('discount_amount', 15, 2)
                ->default(0);

            /*
             * Original sale tax-এর return অংশ।
             */
            $table->decimal('tax_amount', 15, 2)
                ->default(0);

            /*
             * Customer-কে মোট কত টাকা refund দিতে হবে।
             */
            $table->decimal('refund_amount', 15, 2)
                ->default(0);

            /*
             * Possible statuses:
             * draft
             * completed
             * cancelled
             */
            $table->string('status', 30)
                ->default('draft');

            $table->string('reason', 255)
                ->nullable();

            $table->text('notes')
                ->nullable();

            /*
             * Return কে তৈরি করেছে।
             */
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            /*
             * Return কে complete করেছে।
             */
            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('return_date');
            $table->index('status');

            $table->index([
                'sale_id',
                'return_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};