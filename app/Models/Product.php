<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'unit_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'description',
        'image',
        'purchase_price',
        'selling_price',
        'alert_quantity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'alert_quantity' => 'decimal:3',
            'status' => 'boolean',
        ];
    }

    /**
     * Product category relationship.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Product brand relationship.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Product unit relationship.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Product inventory relationship.
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    /**
     * Product stock movement history.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Product purchase items.
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Product sale items.
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Current stock quantity.
     */
    public function currentStock(): float
    {
        return (float) ($this->inventory?->quantity ?? 0);
    }

    /**
     * Reserved stock quantity.
     */
    public function reservedStock(): float
    {
        return (float) (
            $this->inventory?->reserved_quantity ?? 0
        );
    }

    /**
     * Available stock quantity.
     */
    public function availableStock(): float
    {
        return max(
            0,
            $this->currentStock() - $this->reservedStock()
        );
    }

    /**
     * Check whether product stock is low.
     */
    public function isLowStock(): bool
    {
        return $this->currentStock()
            <= (float) $this->alert_quantity;
    }
}