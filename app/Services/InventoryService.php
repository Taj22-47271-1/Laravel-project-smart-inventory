<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Product stock বৃদ্ধি করবে।
     *
     * Purchase receive, sale return অথবা adjustment in-এর
     * সময় এই method ব্যবহার করা হবে।
     */
    public function increaseStock(
        Product $product,
        float $quantity,
        string $movementType,
        ?float $unitCost = null,
        ?Model $reference = null,
        ?string $notes = null,
        User|int|null $createdBy = null
    ): StockMovement {
        $this->validateQuantity($quantity);

        $this->validateIncreaseMovementType($movementType);

        return DB::transaction(function () use (
            $product,
            $quantity,
            $movementType,
            $unitCost,
            $reference,
            $notes,
            $createdBy
        ): StockMovement {
            $inventory = $this->getLockedInventory($product);

            $stockBefore = (float) $inventory->quantity;
            $stockAfter = $stockBefore + $quantity;

            $averageCost = $this->calculateAverageCost(
                currentQuantity: $stockBefore,
                currentAverageCost: (float) $inventory->average_cost,
                addedQuantity: $quantity,
                addedUnitCost: $unitCost
            );

            $inventory->update([
                'quantity' => $stockAfter,
                'average_cost' => $averageCost,
            ]);

            return StockMovement::create([
                'product_id' => $product->id,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_cost' => $unitCost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by' => $createdBy ?? auth()->id(),
            ]);
        }, attempts: 3);
    }

    /**
     * Product stock হ্রাস করবে।
     *
     * Sale, purchase return অথবা adjustment out-এর
     * সময় এই method ব্যবহার করা হবে।
     */
    public function decreaseStock(
        Product $product,
        float $quantity,
        string $movementType,
        ?float $unitCost = null,
        ?Model $reference = null,
        ?string $notes = null,
        User|int|null $createdBy = null
    ): StockMovement {
        $this->validateQuantity($quantity);

        $this->validateDecreaseMovementType($movementType);

        return DB::transaction(function () use (
            $product,
            $quantity,
            $movementType,
            $unitCost,
            $reference,
            $notes,
            $createdBy
        ): StockMovement {
            $inventory = $this->getLockedInventory($product);

            $stockBefore = (float) $inventory->quantity;

            if ($quantity > $stockBefore) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Insufficient stock for %s. Available stock: %s.',
                        $product->name,
                        number_format($stockBefore, 3)
                    ),
                ]);
            }

            $stockAfter = $stockBefore - $quantity;

            $inventory->update([
                'quantity' => $stockAfter,
                'average_cost' => $stockAfter > 0
                    ? $inventory->average_cost
                    : 0,
            ]);

            return StockMovement::create([
                'product_id' => $product->id,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_cost' => $unitCost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by' => $createdBy ?? auth()->id(),
            ]);
        }, attempts: 3);
    }

    /**
     * Product-এর বর্তমান stock return করবে।
     */
    public function currentStock(Product $product): float
    {
        return (float) Inventory::query()
            ->where('product_id', $product->id)
            ->value('quantity');
    }

    /**
     * Inventory record তৈরি করে row lock করবে।
     */
    private function getLockedInventory(Product $product): Inventory
    {
        $inventory = Inventory::query()->firstOrCreate(
            [
                'product_id' => $product->id,
            ],
            [
                'quantity' => 0,
                'reserved_quantity' => 0,
                'average_cost' => 0,
            ]
        );

        return Inventory::query()
            ->whereKey($inventory->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Weighted average purchase cost হিসাব করবে।
     */
    private function calculateAverageCost(
        float $currentQuantity,
        float $currentAverageCost,
        float $addedQuantity,
        ?float $addedUnitCost
    ): float {
        if ($addedUnitCost === null) {
            return $currentAverageCost;
        }

        $newQuantity = $currentQuantity + $addedQuantity;

        if ($newQuantity <= 0) {
            return 0;
        }

        $currentStockValue =
            $currentQuantity * $currentAverageCost;

        $addedStockValue =
            $addedQuantity * $addedUnitCost;

        return round(
            ($currentStockValue + $addedStockValue)
            / $newQuantity,
            2
        );
    }

    /**
     * Quantity valid কি না check করবে।
     */
    private function validateQuantity(float $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock quantity must be greater than zero.',
            ]);
        }
    }

    /**
     * Stock বৃদ্ধির movement type check করবে।
     */
    private function validateIncreaseMovementType(
        string $movementType
    ): void {
        $allowedTypes = [
            StockMovement::TYPE_PURCHASE,
            StockMovement::TYPE_SALE_RETURN,
            StockMovement::TYPE_ADJUSTMENT_IN,
        ];

        if (! in_array($movementType, $allowedTypes, true)) {
            throw new InvalidArgumentException(
                'Invalid stock increase movement type.'
            );
        }
    }

    /**
     * Stock কমানোর movement type check করবে।
     */
    private function validateDecreaseMovementType(
        string $movementType
    ): void {
        $allowedTypes = [
            StockMovement::TYPE_SALE,
            StockMovement::TYPE_PURCHASE_RETURN,
            StockMovement::TYPE_ADJUSTMENT_OUT,
        ];

        if (! in_array($movementType, $allowedTypes, true)) {
            throw new InvalidArgumentException(
                'Invalid stock decrease movement type.'
            );
        }
    }
}
