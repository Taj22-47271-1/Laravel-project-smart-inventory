<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Mass assignable attributes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'quantity',
        'reserved_quantity',
        'average_cost',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'average_cost' => 'decimal:2',
        ];
    }

    /**
     * Inventory-এর product relationship।
     */
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * বর্তমানে বিক্রি করার জন্য available quantity।
     */
    public function availableQuantity(): float
    {
        return max(
            0,
            (float) $this->quantity
            - (float) $this->reserved_quantity
        );
    }

    /**
     * Product low stock-এ আছে কি না।
     */
    public function isLowStock(): bool
    {
        return (float) $this->quantity
            <= (float) $this->product->alert_quantity;
    }
}
