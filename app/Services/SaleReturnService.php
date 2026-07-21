<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleReturnService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function complete(SaleReturn $saleReturn, User $user): SaleReturn
    {
        $this->ensurePermission($user, 'complete sale returns');

        return DB::transaction(function () use ($saleReturn, $user): SaleReturn {
            $saleReturn = SaleReturn::query()
                ->lockForUpdate()
                ->findOrFail($saleReturn->id);

            if (! $saleReturn->isDraft()) {
                throw ValidationException::withMessages([
                    'sale_return' => 'Only draft sale returns can be completed.',
                ]);
            }

            $sale = Sale::query()
                ->lockForUpdate()
                ->findOrFail($saleReturn->sale_id);

            if (! $sale->isCompleted()) {
                throw ValidationException::withMessages([
                    'sale_return' => 'Only completed sales can be returned.',
                ]);
            }

            $saleReturn->load('items');

            if ($saleReturn->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'sale_return' => 'Add at least one returned product.',
                ]);
            }

            foreach ($saleReturn->items as $returnItem) {
                $saleItem = SaleItem::query()
                    ->lockForUpdate()
                    ->findOrFail($returnItem->sale_item_id);

                if ($saleItem->sale_id !== $sale->id || $saleItem->product_id !== $returnItem->product_id) {
                    throw ValidationException::withMessages([
                        'sale_return' => 'A return item does not belong to the selected sale.',
                    ]);
                }

                $returnQuantity = (float) $returnItem->quantity;
                $remaining = max(
                    0,
                    (float) $saleItem->quantity - (float) $saleItem->returned_quantity
                );

                if ($returnQuantity <= 0 || $returnQuantity > $remaining) {
                    throw ValidationException::withMessages([
                        'sale_return' => 'Return quantity exceeds the remaining returnable quantity.',
                    ]);
                }

                /** @var Product $product */
                /** @var Product $product */
                $product = Product::query()
                    ->withTrashed()
                    ->with('inventory')
                    ->findOrFail($returnItem->product_id);

                $unitCost = (float) (
                    optional($product->inventory)->average_cost
                    ?? $product->purchase_price
                    ?? 0
                );

                $this->inventoryService->increaseStock(
                    $product,
                    $returnQuantity,
                    StockMovement::TYPE_SALE_RETURN,
                    $unitCost,
                    $saleReturn,
                    'Stock restored for sale return '.$saleReturn->return_number,
                    $user
                );

                $saleItem->increment('returned_quantity', $returnQuantity);
            }

            $saleReturn->update([
                'status' => SaleReturn::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => now(),
            ]);

            return $saleReturn->fresh([
                'sale.customer',
                'items.product',
                'createdBy',
                'completedBy',
            ]);
        }, attempts: 3);
    }

    public function cancel(SaleReturn $saleReturn, User $user): SaleReturn
    {
        $this->ensurePermission($user, 'cancel sale returns');

        return DB::transaction(function () use ($saleReturn): SaleReturn {
            $saleReturn = SaleReturn::query()
                ->lockForUpdate()
                ->findOrFail($saleReturn->id);

            if (! $saleReturn->isDraft()) {
                throw ValidationException::withMessages([
                    'sale_return' => 'Only draft sale returns can be cancelled.',
                ]);
            }

            $saleReturn->update([
                'status' => SaleReturn::STATUS_CANCELLED,
            ]);

            return $saleReturn->fresh(['sale', 'items.product']);
        }, attempts: 3);
    }

    private function ensurePermission(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }
}
