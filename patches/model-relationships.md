# Existing model patches

Apply these additions to the models already present in your project.

## `app/Models/Sale.php`

Add this import:

```php
use Illuminate\Database\Eloquent\Relations\MorphMany;
```

Keep your existing `saleReturns()` method and add:

```php
public function payments(): MorphMany
{
    return $this->morphMany(Payment::class, 'payable');
}
```

## `app/Models/Purchase.php`

Add these imports if missing:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
```

Add these methods:

```php
public function purchaseReturns(): HasMany
{
    return $this->hasMany(PurchaseReturn::class);
}

public function payments(): MorphMany
{
    return $this->morphMany(Payment::class, 'payable');
}
```

## `app/Models/SaleItem.php`

Add this import:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

Add:

```php
public function returnItems(): HasMany
{
    return $this->hasMany(SaleReturnItem::class);
}
```

## `app/Models/PurchaseItem.php`

Add this import:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

Add:

```php
public function returnItems(): HasMany
{
    return $this->hasMany(PurchaseReturnItem::class);
}
```

## `app/Models/User.php`

Add `is_active` to `$fillable` when your model uses `$fillable`:

```php
'is_active',
```

Add it to `casts()`:

```php
'is_active' => 'boolean',
```

## `app/Models/Product.php` (optional convenience relationships)

`HasMany` is already imported in your current Product model. Add:

```php
public function saleReturnItems(): HasMany
{
    return $this->hasMany(SaleReturnItem::class);
}

public function purchaseReturnItems(): HasMany
{
    return $this->hasMany(PurchaseReturnItem::class);
}

public function stockAdjustmentItems(): HasMany
{
    return $this->hasMany(StockAdjustmentItem::class);
}
```
