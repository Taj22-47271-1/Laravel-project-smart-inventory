<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'received_quantity',
        'unit_cost',
        'discount_amount',
        'tax_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * Parent purchase relationship.
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Purchased product relationship.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Return records created against this purchase item.
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    /**
     * এখনো কত quantity receive করা বাকি।
     */
    public function remainingReceivableQuantity(): float
    {
        return max(
            0,
            (float) $this->quantity
            - (float) $this->received_quantity
        );
    }

    /**
     * Purchase return-এর জন্য available quantity।
     */
    public function returnableQuantity(): float
    {
        $alreadyReturned = (float) $this->returnItems()
            ->whereHas(
                'purchaseReturn',
                fn ($query) => $query->where(
                    'status',
                    PurchaseReturn::STATUS_COMPLETED
                )
            )
            ->sum('quantity');

        return max(
            0,
            (float) $this->received_quantity
            - $alreadyReturned
        );
    }

    public function hasReceivableQuantity(): bool
    {
        return $this->remainingReceivableQuantity() > 0;
    }

    public function hasReturnableQuantity(): bool
    {
        return $this->returnableQuantity() > 0;
    }
}