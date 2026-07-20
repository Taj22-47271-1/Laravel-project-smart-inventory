<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_payment_updates_paid_due_and_payment_status(): void
    {
        Permission::findOrCreate('create payments', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('create payments');

        $sale = Sale::query()->create([
            'sale_number' => 'SAL-TEST-001',
            'customer_id' => null,
            'sale_date' => now()->format('Y-m-d'),
            'subtotal' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'grand_total' => 1000,
            'paid_amount' => 200,
            'due_amount' => 800,
            'status' => Sale::STATUS_COMPLETED,
            'payment_status' => Sale::PAYMENT_PARTIAL,
            'notes' => null,
            'created_by' => $user->id,
        ]);

        $payment = app(PaymentService::class)->recordSalePayment(
            $sale,
            300,
            now()->format('Y-m-d'),
            Payment::METHOD_CASH,
            'TEST-REF',
            null,
            $user
        );

        $sale->refresh();

        $this->assertSame('500.00', $sale->paid_amount);
        $this->assertSame('500.00', $sale->due_amount);
        $this->assertSame(Sale::PAYMENT_PARTIAL, $sale->payment_status);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'amount' => 300,
            'direction' => Payment::DIRECTION_RECEIPT,
        ]);
    }
}
