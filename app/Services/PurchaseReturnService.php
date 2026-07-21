<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function complete(PurchaseReturn $purchaseReturn, User $user): PurchaseReturn
    {
        $this->ensurePermission($user, 'complete purchase returns');

        return DB::transaction(function () use ($purchaseReturn, $user): PurchaseReturn {
            $purchaseReturn = PurchaseReturn::query()
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->id);

            if (! $purchaseReturn->isDraft()) {
                throw ValidationException::withMessages([
                    'purchase_return' => 'Only draft purchase returns can be completed.',
                ]);
            }

            $purchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->purchase_id);

            if ($purchase->status !== Purchase::STATUS_RECEIVED) {
                throw ValidationException::withMessages([
                    'purchase_return' => 'Only received purchases can be returned.',
                ]);
            }

            $purchaseReturn->load('items');

            if ($purchaseReturn->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_return' => 'Add at least one returned product.',
                ]);
            }

            foreach ($purchaseReturn->items as $returnItem) {
                $purchaseItem = PurchaseItem::query()
                    ->lockForUpdate()
                    ->findOrFail($returnItem->purchase_item_id);

                if ($purchaseItem->purchase_id !== $purchase->id || $purchaseItem->product_id !== $returnItem->product_id) {
                    throw ValidationException::withMessages([
                        'purchase_return' => 'A return item does not belong to the selected purchase.',
                    ]);
                }

                $alreadyReturned = (float) PurchaseReturnItem::query()
                    ->where('purchase_item_id', $purchaseItem->id)
                    ->whereHas('purchaseReturn', fn ($query) => $query->where(
                        'status',
                        PurchaseReturn::STATUS_COMPLETED
                    ))
                    ->sum('quantity');

                $remaining = max(
                    0,
                    (float) $purchaseItem->received_quantity - $alreadyReturned
                );

                $returnQuantity = (float) $returnItem->quantity;

                if ($returnQuantity <= 0 || $returnQuantity > $remaining) {
                    throw ValidationException::withMessages([
                        'purchase_return' => 'Return quantity exceeds the received quantity still available for return.',
                    ]);
                }

                /** @var Product $product */
                $product = Product::query()
                    ->withTrashed()
                    ->with('inventory')
                    ->findOrFail($returnItem->product_id);

                $this->inventoryService->decreaseStock(
                    $product,
                    $returnQuantity,
                    StockMovement::TYPE_PURCHASE_RETURN,
                    (float) $returnItem->unit_cost,
                    $purchaseReturn,
                    'Stock reduced for purchase return '.$purchaseReturn->return_number,
                    $user
                );
            }

            $purchaseReturn->update([
                'status' => PurchaseReturn::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => now(),
            ]);

            return $purchaseReturn->fresh([
                'purchase.supplier',
                'items.product',
                'createdBy',
                'completedBy',
            ]);
        }, attempts: 3);
    }

    public function cancel(PurchaseReturn $purchaseReturn, User $user): PurchaseReturn
    {
        $this->ensurePermission($user, 'cancel purchase returns');

        return DB::transaction(function () use ($purchaseReturn): PurchaseReturn {
            $purchaseReturn = PurchaseReturn::query()
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->id);

            if (! $purchaseReturn->isDraft()) {
                throw ValidationException::withMessages([
                    'purchase_return' => 'Only draft purchase returns can be cancelled.',
                ]);
            }

            $purchaseReturn->update([
                'status' => PurchaseReturn::STATUS_CANCELLED,
            ]);

            return $purchaseReturn->fresh(['purchase', 'items.product']);
        }, attempts: 3);
    }

    private function ensurePermission(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }
}
