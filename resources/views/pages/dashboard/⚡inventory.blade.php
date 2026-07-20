<?php

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function stats(): array
    {
        $today = now()->format('Y-m-d');

        return [
            'today_sales' => (float) Sale::query()->where('status', Sale::STATUS_COMPLETED)->whereDate('sale_date', $today)->sum('grand_total'),
            'today_purchases' => (float) Purchase::query()->where('status', Purchase::STATUS_RECEIVED)->whereDate('purchase_date', $today)->sum('grand_total'),
            'customer_due' => (float) Sale::query()->where('status', Sale::STATUS_COMPLETED)->sum('due_amount'),
            'supplier_due' => (float) Purchase::query()->where('status', Purchase::STATUS_RECEIVED)->sum('due_amount'),
            'inventory_value' => (float) Inventory::query()->selectRaw('COALESCE(SUM(quantity * average_cost),0) AS value')->value('value'),
            'low_stock' => Product::query()->leftJoin('inventories','inventories.product_id','=','products.id')->whereRaw('COALESCE(inventories.quantity,0) <= products.alert_quantity')->count('products.id'),
        ];
    }

    #[Computed]
    public function recentSales()
    {
        return Sale::query()->with('customer:id,name')->latest()->limit(5)->get();
    }

    #[Computed]
    public function recentMovements()
    {
        return StockMovement::query()->with('product:id,name,sku')->latest()->limit(8)->get();
    }
};
?>

<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">Inventory Dashboard</h1><p class="text-sm text-gray-500">Operational overview of sales, purchases, stock and dues.</p></div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Today's Sales</p><p class="mt-2 text-2xl font-bold text-green-600">৳{{ number_format($this->stats['today_sales'],2) }}</p></div>
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Today's Purchases</p><p class="mt-2 text-2xl font-bold text-blue-600">৳{{ number_format($this->stats['today_purchases'],2) }}</p></div>
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Customer Due</p><p class="mt-2 text-2xl font-bold text-red-600">৳{{ number_format($this->stats['customer_due'],2) }}</p></div>
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Supplier Due</p><p class="mt-2 text-2xl font-bold text-amber-600">৳{{ number_format($this->stats['supplier_due'],2) }}</p></div>
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Inventory Value</p><p class="mt-2 text-2xl font-bold text-purple-600">৳{{ number_format($this->stats['inventory_value'],2) }}</p></div>
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Low Stock</p><p class="mt-2 text-2xl font-bold text-orange-600">{{ number_format($this->stats['low_stock']) }}</p></div>
    </div>
    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-xl border bg-white dark:bg-gray-900"><div class="border-b p-5"><h2 class="font-semibold">Recent Sales</h2></div><div class="divide-y">@forelse ($this->recentSales as $sale)<div class="flex items-center justify-between p-4"><div><p class="font-semibold">{{ $sale->sale_number }}</p><p class="text-xs text-gray-500">{{ $sale->customer?->name ?? 'Walk-in Customer' }}</p></div><div class="text-right"><p class="font-semibold">৳{{ number_format((float)$sale->grand_total,2) }}</p><p class="text-xs capitalize text-gray-500">{{ $sale->status }}</p></div></div>@empty<div class="p-8 text-center text-gray-500">No sales yet.</div>@endforelse</div></div>
        <div class="rounded-xl border bg-white dark:bg-gray-900"><div class="border-b p-5"><h2 class="font-semibold">Recent Stock Movements</h2></div><div class="divide-y">@forelse ($this->recentMovements as $movement)<div class="flex items-center justify-between p-4"><div><p class="font-semibold">{{ $movement->product->name }}</p><p class="text-xs text-gray-500">{{ str_replace('_',' ',$movement->movement_type) }}</p></div><div class="text-right"><p class="font-semibold">{{ number_format((float)$movement->quantity,3) }}</p><p class="text-xs text-gray-500">{{ $movement->created_at->format('d M, h:i A') }}</p></div></div>@empty<div class="p-8 text-center text-gray-500">No stock movement yet.</div>@endforelse</div></div>
    </div>
</div>
