# `routes/web.php` patch

Add these declarations after the existing routes. Do not add a second `<?php` tag.

```php
Route::middleware(['auth', 'verified', 'active'])->group(function (): void {
    Route::livewire('/sale-returns', 'pages::sale-returns.index')
        ->middleware('permission:view sale returns')
        ->name('sale-returns.index');

    Route::livewire('/sale-returns/manage', 'pages::sale-returns.manage')
        ->middleware('permission:view sale returns')
        ->name('sale-returns.manage');

    Route::livewire('/purchase-returns', 'pages::purchase-returns.index')
        ->middleware('permission:view purchase returns')
        ->name('purchase-returns.index');

    Route::livewire('/purchase-returns/manage', 'pages::purchase-returns.manage')
        ->middleware('permission:view purchase returns')
        ->name('purchase-returns.manage');

    Route::livewire('/stock-adjustments', 'pages::stock-adjustments.index')
        ->middleware('permission:view stock adjustments')
        ->name('stock-adjustments.index');

    Route::livewire('/stock-adjustments/manage', 'pages::stock-adjustments.manage')
        ->middleware('permission:view stock adjustments')
        ->name('stock-adjustments.manage');

    Route::livewire('/payments', 'pages::payments.index')
        ->middleware('permission:view payments')
        ->name('payments.index');

    Route::livewire('/reports', 'pages::reports.index')
        ->middleware('permission:view reports')
        ->name('reports.index');

    Route::livewire('/users/manage', 'pages::users.index')
        ->middleware('permission:view users')
        ->name('users.index');

    Route::livewire('/inventory-dashboard', 'pages::dashboard.inventory')
        ->middleware('permission:view dashboard')
        ->name('inventory-dashboard');
});
```
