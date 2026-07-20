<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome')
    ->name('home');

/*
|--------------------------------------------------------------------------
| Authenticated Smart Inventory Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'verified',
    'active',
])->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/dashboard',
        'pages::dashboard.inventory'
    )
        ->middleware('permission:view dashboard')
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Product Management
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/categories',
        'pages::categories.index'
    )
        ->middleware('permission:view categories')
        ->name('categories.index');

    Route::livewire(
        '/brands',
        'pages::brands.index'
    )
        ->middleware('permission:view brands')
        ->name('brands.index');

    Route::livewire(
        '/units',
        'pages::units.index'
    )
        ->middleware('permission:view units')
        ->name('units.index');

    Route::livewire(
        '/products',
        'pages::products.index'
    )
        ->middleware('permission:view products')
        ->name('products.index');

    /*
    |--------------------------------------------------------------------------
    | Supplier and Customer Management
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/suppliers',
        'pages::suppliers.index'
    )
        ->middleware('permission:view suppliers')
        ->name('suppliers.index');

    Route::livewire(
        '/customers',
        'pages::customers.index'
    )
        ->middleware('permission:view customers')
        ->name('customers.index');

    /*
    |--------------------------------------------------------------------------
    | Purchase Management
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/purchases',
        'pages::purchases.index'
    )
        ->middleware('permission:view purchases')
        ->name('purchases.index');

    Route::livewire(
        '/purchases/manage',
        'pages::purchases.manage'
    )
        ->middleware('permission:view purchases')
        ->name('purchases.manage');

    /*
    |--------------------------------------------------------------------------
    | Sales Management
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/sales',
        'pages::sales.index'
    )
        ->middleware('permission:view sales')
        ->name('sales.index');

    Route::livewire(
        '/sales/manage',
        'pages::sales.manage'
    )
        ->middleware('permission:view sales')
        ->name('sales.manage');

    /*
    |--------------------------------------------------------------------------
    | Inventory Management
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/inventory',
        'pages::inventory.index'
    )
        ->middleware('permission:view inventory')
        ->name('inventory.index');

    Route::livewire(
        '/inventory/movements',
        'pages::inventory.movements'
    )
        ->middleware('permission:view inventory')
        ->name('inventory.movements');

    /*
    |--------------------------------------------------------------------------
    | Sale Returns
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/sale-returns',
        'pages::sale-returns.index'
    )
        ->middleware('permission:view sale returns')
        ->name('sale-returns.index');

    Route::livewire(
        '/sale-returns/manage',
        'pages::sale-returns.manage'
    )
        ->middleware('permission:view sale returns')
        ->name('sale-returns.manage');

    /*
    |--------------------------------------------------------------------------
    | Purchase Returns
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/purchase-returns',
        'pages::purchase-returns.index'
    )
        ->middleware('permission:view purchase returns')
        ->name('purchase-returns.index');

    Route::livewire(
        '/purchase-returns/manage',
        'pages::purchase-returns.manage'
    )
        ->middleware('permission:view purchase returns')
        ->name('purchase-returns.manage');

    /*
    |--------------------------------------------------------------------------
    | Stock Adjustments
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/stock-adjustments',
        'pages::stock-adjustments.index'
    )
        ->middleware('permission:view stock adjustments')
        ->name('stock-adjustments.index');

    Route::livewire(
        '/stock-adjustments/manage',
        'pages::stock-adjustments.manage'
    )
        ->middleware('permission:view stock adjustments')
        ->name('stock-adjustments.manage');

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/payments',
        'pages::payments.index'
    )
        ->middleware('permission:view payments')
        ->name('payments.index');

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/reports',
        'pages::reports.index'
    )
        ->middleware('permission:view reports')
        ->name('reports.index');

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */

    Route::livewire(
        '/users/manage',
        'pages::users.index'
    )
        ->middleware('permission:view users')
        ->name('users.index');
});

/*
|--------------------------------------------------------------------------
| Starter Kit Settings Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';