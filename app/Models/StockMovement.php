<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory;

    /**
     * Stock movement types.
     */
    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_SALE = 'sale';

    public const TYPE_PURCHASE_RETURN = 'purchase_return';

    public const TYPE_SALE_RETURN = 'sale_return';

    public const TYPE_ADJUSTMENT_IN = 'adjustment_in';

    public const TYPE_ADJUSTMENT_OUT = 'adjustment_out';

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'movement_type',
        'quantity',
        'stock_before',
        'stock_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
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
            'stock_before' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * Product relationship.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * User who created the stock movement.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * Related purchase, sale or return record.
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check whether this movement increases stock.
     */
    public function increasesStock(): bool
    {
        return in_array($this->movement_type, [
            self::TYPE_PURCHASE,
            self::TYPE_SALE_RETURN,
            self::TYPE_ADJUSTMENT_IN,
        ], true);
    }

    /**
     * Check whether this movement decreases stock.
     */
    public function decreasesStock(): bool
    {
        return in_array($this->movement_type, [
            self::TYPE_SALE,
            self::TYPE_PURCHASE_RETURN,
            self::TYPE_ADJUSTMENT_OUT,
        ], true);
    }
}