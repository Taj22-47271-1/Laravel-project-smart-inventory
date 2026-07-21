<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function complete(StockAdjustment $adjustment, User $user): StockAdjustment
    {
        $this->ensurePermission($user, 'complete stock adjustments');

        return DB::transaction(function () use ($adjustment, $user): StockAdjustment {
            $adjustment = StockAdjustment::query()
                ->lockForUpdate()
                ->findOrFail($adjustment->id);

            if (! $adjustment->isDraft()) {
                throw ValidationException::withMessages([
                    'adjustment' => 'Only draft adjustments can be completed.',
                ]);
            }

            $adjustment->load('items');

            if ($adjustment->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'adjustment' => 'Add at least one adjustment item.',
                ]);
            }

            foreach ($adjustment->items as $item) {
                $product = Product::query()
                    ->withTrashed()
                    ->with('inventory')
                    ->findOrFail($item->product_id);

                $quantity = (float) $item->quantity;
                $unitCost = $item->unit_cost !== null
                    ? (float) $item->unit_cost
                    : (float) ($product->inventory?->average_cost ?? $product->purchase_price ?? 0);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'adjustment' => 'Adjustment quantity must be greater than zero.',
                    ]);
                }

                if ($item->direction === StockAdjustmentItem::DIRECTION_IN) {
                    $this->inventoryService->increaseStock(
                        $product,
                        $quantity,
                        StockMovement::TYPE_ADJUSTMENT_IN,
                        $unitCost,
                        $adjustment,
                        $item->notes ?: 'Stock adjustment in '.$adjustment->adjustment_number,
                        $user
                    );
                } elseif ($item->direction === StockAdjustmentItem::DIRECTION_OUT) {
                    $this->inventoryService->decreaseStock(
                        $product,
                        $quantity,
                        StockMovement::TYPE_ADJUSTMENT_OUT,
                        $unitCost,
                        $adjustment,
                        $item->notes ?: 'Stock adjustment out '.$adjustment->adjustment_number,
                        $user
                    );
                } else {
                    throw ValidationException::withMessages([
                        'adjustment' => 'Invalid stock adjustment direction.',
                    ]);
                }
            }

            $adjustment->update([
                'status' => StockAdjustment::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => now(),
            ]);

            return $adjustment->fresh(['items.product', 'createdBy', 'completedBy']);
        }, attempts: 3);
    }

    public function cancel(StockAdjustment $adjustment, User $user): StockAdjustment
    {
        $this->ensurePermission($user, 'cancel stock adjustments');

        return DB::transaction(function () use ($adjustment): StockAdjustment {
            $adjustment = StockAdjustment::query()
                ->lockForUpdate()
                ->findOrFail($adjustment->id);

            if (! $adjustment->isDraft()) {
                throw ValidationException::withMessages([
                    'adjustment' => 'Only draft adjustments can be cancelled.',
                ]);
            }

            $adjustment->update(['status' => StockAdjustment::STATUS_CANCELLED]);

            return $adjustment->fresh(['items.product']);
        }, attempts: 3);
    }

    private function ensurePermission(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }
}
