<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    /**
     * InventoryService dependency inject করবে।
     */
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Draft purchase approval-এর জন্য submit করবে।
     */
    public function submitForApproval(
        Purchase $purchase,
        User $submittedBy
    ): Purchase {
        $this->ensurePermission(
            $submittedBy,
            'create purchases'
        );

        return DB::transaction(function () use (
            $purchase
        ): Purchase {
            $lockedPurchase = $this->lockPurchase(
                $purchase
            );

            if (
                $lockedPurchase->status
                !== Purchase::STATUS_DRAFT
            ) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only draft purchases can be submitted for approval.',
                ]);
            }

            if (! $lockedPurchase->items()->exists()) {
                throw ValidationException::withMessages([
                    'purchase' => 'A purchase must contain at least one product.',
                ]);
            }

            $lockedPurchase->update([
                'status' => Purchase::STATUS_PENDING,
            ]);

            return $this->freshPurchase(
                $lockedPurchase
            );
        }, attempts: 3);
    }

    /**
     * Pending purchase approve করবে।
     */
    public function approve(
        Purchase $purchase,
        User $approvedBy
    ): Purchase {
        $this->ensurePermission(
            $approvedBy,
            'approve purchases'
        );

        return DB::transaction(function () use (
            $purchase,
            $approvedBy
        ): Purchase {
            $lockedPurchase = $this->lockPurchase(
                $purchase
            );

            if (
                $lockedPurchase->status
                !== Purchase::STATUS_PENDING
            ) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only pending purchases can be approved.',
                ]);
            }

            if (! $lockedPurchase->items()->exists()) {
                throw ValidationException::withMessages([
                    'purchase' => 'A purchase must contain at least one product.',
                ]);
            }

            $lockedPurchase->update([
                'status' => Purchase::STATUS_APPROVED,
                'approved_by' => $approvedBy->id,
                'approved_at' => now(),
            ]);

            return $this->freshPurchase(
                $lockedPurchase
            );
        }, attempts: 3);
    }

    /**
     * Approved purchase receive করে stock বাড়াবে।
     */
    public function receive(
        Purchase $purchase,
        User $receivedBy
    ): Purchase {
        $this->ensureReceivePermission(
            $receivedBy
        );

        return DB::transaction(function () use (
            $purchase,
            $receivedBy
        ): Purchase {
            $lockedPurchase = $this->lockPurchase(
                $purchase
            );

            if (
                $lockedPurchase->status
                !== Purchase::STATUS_APPROVED
            ) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only approved purchases can be received.',
                ]);
            }

            $items = $lockedPurchase
                ->items()
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase' => 'This purchase does not contain any products.',
                ]);
            }

            foreach ($items as $item) {
                $orderedQuantity =
                    (float) $item->quantity;

                $alreadyReceived =
                    (float) $item->received_quantity;

                $remainingQuantity = round(
                    max(
                        0,
                        $orderedQuantity - $alreadyReceived
                    ),
                    3
                );

                /*
                 * Item আগেই সম্পূর্ণ receive হয়ে থাকলে
                 * দ্বিতীয়বার stock বাড়াবে না।
                 */
                if ($remainingQuantity <= 0) {
                    continue;
                }

                /*
                 * Soft-deleted product হলেও পুরোনো
                 * approved purchase receive করা যাবে।
                 */
                $product = Product::withTrashed()
                    ->findOrFail($item->product_id);

                $this->inventoryService->increaseStock(
                    product: $product,
                    quantity: $remainingQuantity,
                    movementType: StockMovement::TYPE_PURCHASE,
                    unitCost: (float) $item->unit_cost,
                    reference: $lockedPurchase,
                    notes: 'Stock received from purchase '
                        .$lockedPurchase->purchase_number.'.',
                    createdBy: $receivedBy->id
                );

                $item->update([
                    'received_quantity' => $orderedQuantity,
                ]);

                /*
                 * Product-এর latest purchase price update করবে।
                 */
                $product->update([
                    'purchase_price' => (float) $item->unit_cost,
                ]);
            }

            $lockedPurchase->update([
                'status' => Purchase::STATUS_RECEIVED,
            ]);

            return $this->freshPurchase(
                $lockedPurchase
            );
        }, attempts: 3);
    }

    /**
     * Draft অথবা pending purchase cancel করবে।
     */
    public function cancel(
        Purchase $purchase,
        User $cancelledBy
    ): Purchase {
        $this->ensurePermission(
            $cancelledBy,
            'delete purchases'
        );

        return DB::transaction(function () use (
            $purchase
        ): Purchase {
            $lockedPurchase = $this->lockPurchase(
                $purchase
            );

            $allowedStatuses = [
                Purchase::STATUS_DRAFT,
                Purchase::STATUS_PENDING,
            ];

            if (
                ! in_array(
                    $lockedPurchase->status,
                    $allowedStatuses,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only draft or pending purchases can be cancelled.',
                ]);
            }

            $lockedPurchase->update([
                'status' => Purchase::STATUS_CANCELLED,
            ]);

            return $this->freshPurchase(
                $lockedPurchase
            );
        }, attempts: 3);
    }

    /**
     * Purchase row lock করে retrieve করবে।
     */
    private function lockPurchase(
        Purchase $purchase
    ): Purchase {
        return Purchase::query()
            ->whereKey($purchase->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Updated purchase এবং relationships reload করবে।
     */
    private function freshPurchase(
        Purchase $purchase
    ): Purchase {
        return $purchase->fresh([
            'supplier',
            'items.product',
            'createdBy',
            'approvedBy',
        ]);
    }

    /**
     * User permission check করবে।
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

    /**
     * Purchase receive permission check করবে।
     */
    private function ensureReceivePermission(
        User $user
    ): void {
        $canReceive =
            $user->can('approve purchases')
            || $user->can('manage inventory');

        if (! $canReceive) {
            throw new AuthorizationException(
                'You do not have permission to receive purchases.'
            );
        }
    }
}
