<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->morphs('payable');
            $table->string('direction', 20);
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('method', 30)->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['payment_date', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
