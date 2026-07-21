<?php

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\ReportService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $dateFrom = '';
    public string $dateTo = '';
    public string $reportType = 'sales';
    public string $search = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedDateFrom(): void { $this->resetPage(); unset($this->summary, $this->rows); }
    public function updatedDateTo(): void { $this->resetPage(); unset($this->summary, $this->rows); }
    public function updatedReportType(): void { $this->resetPage(); unset($this->rows); }
    public function updatedSearch(): void { $this->resetPage(); }

    #[Computed]
    public function summary(): array
    {
        return app(ReportService::class)->summary($this->dateFrom, $this->dateTo);
    }

    #[Computed]
    public function rows()
    {
        $search = trim($this->search);

        return match ($this->reportType) {
            'purchases' => Purchase::query()
                ->with('supplier:id,name,company_name')
                ->whereBetween('purchase_date', [$this->dateFrom, $this->dateTo])
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('purchase_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"));
                }))
                ->latest('purchase_date')
                ->paginate(15),
            'inventory' => Product::query()
                ->with(['category:id,name', 'unit:id,short_name', 'inventory:id,product_id,quantity,reserved_quantity,average_cost'])
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
                }))
                ->orderBy('name')
                ->paginate(15),
            default => Sale::query()
                ->with('customer:id,name')
                ->whereBetween('sale_date', [$this->dateFrom, $this->dateTo])
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('sale_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                }))
                ->latest('sale_date')
                ->paginate(15),
        };
    }
};
?>

<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">Reports</h1><p class="text-sm text-gray-500">Sales, purchases, inventory valuation and due summaries.</p></div>

    <div class="grid gap-4 rounded-xl border bg-white p-5 md:grid-cols-4 dark:bg-gray-900">
        <div><label class="mb-2 block text-sm font-medium">Date From</label><input type="date" wire:model.live="dateFrom" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"></div>
        <div><label class="mb-2 block text-sm font-medium">Date To</label><input type="date" wire:model.live="dateTo" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"></div>
        <div><label class="mb-2 block text-sm font-medium">Report</label><select wire:model.live="reportType" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"><option value="sales">Sales Report</option><option value="purchases">Purchase Report</option><option value="inventory">Inventory Valuation</option></select></div>
        <div><label class="mb-2 block text-sm font-medium">Search</label><input type="search" wire:model.live.debounce.300ms="search" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800" placeholder="Number, name or SKU..."></div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Net Sales</p><p class="mt-2 text-2xl font-bold text-green-600">৳{{ number_format($this->summary['net_sales'],2) }}</p></div>
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Net Purchases</p><p class="mt-2 text-2xl font-bold text-blue-600">৳{{ number_format($this->summary['net_purchases'],2) }}</p></div>
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Margin Estimate</p><p class="mt-2 text-2xl font-bold">৳{{ number_format($this->summary['gross_margin_estimate'],2) }}</p></div>
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Customer Due</p><p class="mt-2 text-2xl font-bold text-red-600">৳{{ number_format($this->summary['customer_due'],2) }}</p></div>
        <div class="rounded-xl border bg-white p-5 dark:bg-gray-900"><p class="text-sm text-gray-500">Inventory Value</p><p class="mt-2 text-2xl font-bold text-purple-600">৳{{ number_format($this->summary['inventory_value'],2) }}</p></div>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white dark:bg-gray-900">
        <div class="border-b p-5"><h2 class="text-lg font-semibold capitalize">{{ $reportType }} Report</h2></div>
        <div class="overflow-x-auto">
            @if ($reportType === 'sales')
                <table class="min-w-[950px] w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-4 py-3">Sale</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Paid</th><th class="px-4 py-3 text-right">Due</th></tr></thead><tbody class="divide-y">@forelse ($this->rows as $row)<tr><td class="px-4 py-4 font-semibold">{{ $row->sale_number }}</td><td class="px-4 py-4">{{ $row->customer?->name ?? 'Walk-in Customer' }}</td><td class="px-4 py-4">{{ $row->sale_date->format('d M Y') }}</td><td class="px-4 py-4 capitalize">{{ $row->status }}</td><td class="px-4 py-4 text-right">৳{{ number_format((float)$row->grand_total,2) }}</td><td class="px-4 py-4 text-right">৳{{ number_format((float)$row->paid_amount,2) }}</td><td class="px-4 py-4 text-right">৳{{ number_format((float)$row->due_amount,2) }}</td></tr>@empty<tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No sales found.</td></tr>@endforelse</tbody></table>
            @elseif ($reportType === 'purchases')
                <table class="min-w-[950px] w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-4 py-3">Purchase</th><th class="px-4 py-3">Supplier</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Paid</th><th class="px-4 py-3 text-right">Due</th></tr></thead><tbody class="divide-y">@forelse ($this->rows as $row)<tr><td class="px-4 py-4 font-semibold">{{ $row->purchase_number }}</td><td class="px-4 py-4">{{ $row->supplier->company_name ?? $row->supplier->name }}</td><td class="px-4 py-4">{{ $row->purchase_date->format('d M Y') }}</td><td class="px-4 py-4 capitalize">{{ $row->status }}</td><td class="px-4 py-4 text-right">৳{{ number_format((float)$row->grand_total,2) }}</td><td class="px-4 py-4 text-right">৳{{ number_format((float)$row->paid_amount,2) }}</td><td class="px-4 py-4 text-right">৳{{ number_format((float)$row->due_amount,2) }}</td></tr>@empty<tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No purchases found.</td></tr>@endforelse</tbody></table>
            @else
                <table class="min-w-[950px] w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-4 py-3">Product</th><th class="px-4 py-3">Category</th><th class="px-4 py-3 text-right">Quantity</th><th class="px-4 py-3 text-right">Reserved</th><th class="px-4 py-3 text-right">Average Cost</th><th class="px-4 py-3 text-right">Stock Value</th></tr></thead><tbody class="divide-y">@forelse ($this->rows as $row)@php $quantity=(float)($row->inventory?->quantity??0); $average=(float)($row->inventory?->average_cost??0); @endphp<tr><td class="px-4 py-4"><p class="font-semibold">{{ $row->name }}</p><p class="text-xs text-gray-500">{{ $row->sku }}</p></td><td class="px-4 py-4">{{ $row->category->name }}</td><td class="px-4 py-4 text-right">{{ number_format($quantity,3) }} {{ $row->unit->short_name }}</td><td class="px-4 py-4 text-right">{{ number_format((float)($row->inventory?->reserved_quantity??0),3) }}</td><td class="px-4 py-4 text-right">৳{{ number_format($average,2) }}</td><td class="px-4 py-4 text-right font-semibold">৳{{ number_format($quantity*$average,2) }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No inventory products found.</td></tr>@endforelse</tbody></table>
            @endif
        </div>
        <div class="border-t p-4">{{ $this->rows->links() }}</div>
    </div>
</div>
