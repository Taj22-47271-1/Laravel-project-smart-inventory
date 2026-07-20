<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {
    }

    /**
     * Draft sale complete করবে এবং product stock কমাবে।
     */
    public function complete(
        Sale $sale,
        User $user
    ): Sale {
        $this->ensurePermission($user, 'create sales');

        return DB::transaction(function () use (
            $sale,
            $user
        ): Sale {
            $sale = $this->lockSale($sale->id);

            if (! $sale->isDraft()) {
                throw ValidationException::withMessages([
                    'sale' => 'Only draft sales can be completed.',
                ]);
            }

            $sale->load('items');

            if ($sale->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'sale' => 'The sale must contain at least one product.',
                ]);
            }

            $grandTotal = (float) $sale->grand_total;
            $paidAmount = (float) $sale->paid_amount;

            if ($grandTotal <= 0) {
                throw ValidationException::withMessages([
                    'sale' => 'Sale grand total must be greater than zero.',
                ]);
            }

            if ($paidAmount < 0) {
                throw ValidationException::withMessages([
                    'sale' => 'Paid amount cannot be negative.',
                ]);
            }

            if ($paidAmount > $grandTotal) {
                throw ValidationException::withMessages([
                    'sale' => 'Paid amount cannot exceed the grand total.',
                ]);
            }

            foreach ($sale->items as $item) {
                $quantity = (float) $item->quantity;

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'sale' => 'Every sale item must have a valid quantity.',
                    ]);
                }

                $product = Product::query()
                    ->withTrashed()
                    ->with('inventory')
                    ->findOrFail($item->product_id);

                $unitCost = (float) (
                    $product->inventory?->average_cost
                    ?? $product->purchase_price
                    ?? 0
                );

                $this->inventoryService->decreaseStock(
                    $product,
                    $quantity,
                    StockMovement::TYPE_SALE,
                    $unitCost,
                    $sale,
                    'Stock reduced for sale '
                        .$sale->sale_number,
                    $user
                );
            }

            $dueAmount = max(
                0,
                $grandTotal - $paidAmount
            );

            $paymentStatus = $this->determinePaymentStatus(
                $grandTotal,
                $paidAmount
            );

            $sale->update([
                'status' => Sale::STATUS_COMPLETED,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'payment_status' => $paymentStatus,
            ]);

            return $this->freshSale($sale);
        }, attempts: 3);
    }

    /**
     * Draft sale cancel করবে।
     */
    public function cancel(
        Sale $sale,
        User $user
    ): Sale {
        $this->ensurePermission($user, 'delete sales');

        return DB::transaction(function () use (
            $sale
        ): Sale {
            $sale = $this->lockSale($sale->id);

            if (! $sale->isDraft()) {
                throw ValidationException::withMessages([
                    'sale' => 'Only draft sales can be cancelled.',
                ]);
            }

            $sale->update([
                'status' => Sale::STATUS_CANCELLED,
            ]);

            return $this->freshSale($sale);
        }, attempts: 3);
    }

    /**
     * Paid amount অনুযায়ী payment status নির্ধারণ করবে।
     */
    private function determinePaymentStatus(
        float $grandTotal,
        float $paidAmount
    ): string {
        if ($paidAmount <= 0) {
            return Sale::PAYMENT_UNPAID;
        }

        if ($paidAmount >= $grandTotal) {
            return Sale::PAYMENT_PAID;
        }

        return Sale::PAYMENT_PARTIAL;
    }

    /**
     * Sale row lock করে retrieve করবে।
     */
    private function lockSale(int $saleId): Sale
    {
        return Sale::query()
            ->lockForUpdate()
            ->findOrFail($saleId);
    }

    /**
     * Updated sale relationships সহ return করবে।
     */
    private function freshSale(Sale $sale): Sale
    {
        return $sale->fresh([
            'customer',
            'items.product',
            'createdBy',
        ]);
    }

    /**
     * User permission পরীক্ষা করবে।
     */
    private function ensurePermission(
        User $user,
        string $permission
    ): void {
        if (! $user->can($permission)) {
            throw new AuthorizationException(
                'You do not have permission to perform this action.'
            );
        }
    }
}