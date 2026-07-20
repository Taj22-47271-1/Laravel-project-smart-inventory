<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'returned_quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'returned_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * Parent sale relationship.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Sold product relationship.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Return records created against this sale item.
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /**
     * Quantity that can still be returned.
     */
    public function remainingReturnableQuantity(): float
    {
        return max(
            0,
            (float) $this->quantity
            - (float) $this->returned_quantity
        );
    }

    /**
     * Check whether this item has returnable quantity.
     */
    public function hasReturnableQuantity(): bool
    {
        return $this->remainingReturnableQuantity() > 0;
    }
}