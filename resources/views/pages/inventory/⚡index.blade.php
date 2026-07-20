<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public string $stockFilter = '';

    /**
     * Search পরিবর্তন হলে প্রথম page-এ যাবে।
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Category filter পরিবর্তন হলে প্রথম page-এ যাবে।
     */
    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Stock filter পরিবর্তন হলে প্রথম page-এ যাবে।
     */
    public function updatedStockFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Category filter-এর data।
     */
    #[Computed]
    public function categories()
    {
        return Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);
    }

    /**
     * Inventory dashboard summary।
     *
     * @return array<string, int|float>
     */
    #[Computed]
    public function summary(): array
    {
        $lowStockProducts = Product::query()
            ->leftJoin(
                'inventories',
                'inventories.product_id',
                '=',
                'products.id'
            )
            ->whereRaw(
                'COALESCE(inventories.quantity, 0)
                <= products.alert_quantity'
            )
            ->count('products.id');

        $outOfStockProducts = Product::query()
            ->leftJoin(
                'inventories',
                'inventories.product_id',
                '=',
                'products.id'
            )
            ->whereRaw(
                'COALESCE(inventories.quantity, 0) <= 0'
            )
            ->count('products.id');

        $stockValue = Inventory::query()
            ->selectRaw(
                'COALESCE(
                    SUM(quantity * average_cost),
                    0
                ) as total_stock_value'
            )
            ->value('total_stock_value');

        return [
            'total_products' => Product::query()->count(),

            'total_quantity' => (float) Inventory::query()
                ->sum('quantity'),

            'low_stock_products' => $lowStockProducts,

            'out_of_stock_products' => $outOfStockProducts,

            'stock_value' => (float) $stockValue,
        ];
    }

    /**
     * Filter করা inventory product list।
     */
    #[Computed]
    public function inventoryProducts()
    {
        $search = trim($this->search);

        return Product::query()
            ->select('products.*')
            ->leftJoin(
                'inventories',
                'inventories.product_id',
                '=',
                'products.id'
            )
            ->with([
                'category:id,name',
                'brand:id,name',
                'unit:id,name,short_name',
                'inventory:id,product_id,quantity,reserved_quantity,average_cost',
            ])
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'products.name',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'products.sku',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'products.barcode',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhereHas(
                                    'category',
                                    fn ($query) => $query->where(
                                        'name',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                )
                                ->orWhereHas(
                                    'brand',
                                    fn ($query) => $query->where(
                                        'name',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                );
                        }
                    );
                }
            )
            ->when(
                $this->categoryFilter !== '',
                fn ($query) => $query->where(
                    'products.category_id',
                    $this->categoryFilter
                )
            )
            ->when(
                $this->stockFilter === 'in_stock',
                fn ($query) => $query->whereRaw(
                    'COALESCE(inventories.quantity, 0)
                    > products.alert_quantity'
                )
            )
            ->when(
                $this->stockFilter === 'low_stock',
                fn ($query) => $query
                    ->whereRaw(
                        'COALESCE(inventories.quantity, 0) > 0'
                    )
                    ->whereRaw(
                        'COALESCE(inventories.quantity, 0)
                        <= products.alert_quantity'
                    )
            )
            ->when(
                $this->stockFilter === 'out_of_stock',
                fn ($query) => $query->whereRaw(
                    'COALESCE(inventories.quantity, 0) <= 0'
                )
            )
            ->orderBy('products.name')
            ->paginate(10);
    }
};

?>

<div class="space-y-6">
    {{-- Page heading --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Inventory Management
        </h1>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Monitor current stock, available quantity and inventory value.
        </p>
    </div>

    {{-- Summary cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {{-- Total products --}}
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Total Products
            </p>

            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">
                {{ number_format($this->summary['total_products']) }}
            </p>
        </div>

        {{-- Total stock --}}
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Total Stock Quantity
            </p>

            <p class="mt-3 text-3xl font-bold text-blue-600">
                {{ number_format(
                    $this->summary['total_quantity'],
                    3
                ) }}
            </p>
        </div>

        {{-- Inventory value --}}
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Inventory Value
            </p>

            <p class="mt-3 text-3xl font-bold text-green-600">
                ৳{{ number_format(
                    $this->summary['stock_value'],
                    2
                ) }}
            </p>
        </div>

        {{-- Low stock --}}
        <div
            class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/40"
        >
            <p class="text-sm font-medium text-amber-700 dark:text-amber-300">
                Low-Stock Products
            </p>

            <p class="mt-3 text-3xl font-bold text-amber-700 dark:text-amber-300">
                {{ number_format(
                    $this->summary['low_stock_products']
                ) }}
            </p>
        </div>

        {{-- Out of stock --}}
        <div
            class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-900 dark:bg-red-950/40"
        >
            <p class="text-sm font-medium text-red-700 dark:text-red-300">
                Out-of-Stock Products
            </p>

            <p class="mt-3 text-3xl font-bold text-red-700 dark:text-red-300">
                {{ number_format(
                    $this->summary['out_of_stock_products']
                ) }}
            </p>
        </div>
    </div>

    {{-- Inventory table --}}
    <div
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        {{-- Filters --}}
        <div class="border-b border-gray-200 p-5 dark:border-gray-700">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Product Inventory
                </h2>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Search and filter current product stock.
                </p>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-3">
                {{-- Search --}}
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search product, SKU or barcode..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >

                {{-- Category --}}
                <select
                    wire:model.live="categoryFilter"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">All categories</option>

                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

                {{-- Stock status --}}
                <select
                    wire:model.live="stockFilter"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">All stock statuses</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="out_of_stock">
                        Out of Stock
                    </option>
                </select>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-[1200px] w-full text-left text-sm">
                <thead
                    class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                >
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Brand</th>
                        <th class="px-4 py-3 text-right">
                            Current Stock
                        </th>
                        <th class="px-4 py-3 text-right">
                            Reserved
                        </th>
                        <th class="px-4 py-3 text-right">
                            Available
                        </th>
                        <th class="px-4 py-3 text-right">
                            Alert Level
                        </th>
                        <th class="px-4 py-3 text-right">
                            Average Cost
                        </th>
                        <th class="px-4 py-3 text-right">
                            Stock Value
                        </th>
                        <th class="px-4 py-3">
                            Stock Status
                        </th>
                    </tr>
                </thead>

                <tbody
                    class="divide-y divide-gray-200 dark:divide-gray-700"
                >
                    @forelse (
                        $this->inventoryProducts as $product
                    )
                        @php
                            $currentStock = (float) (
                                $product->inventory?->quantity ?? 0
                            );

                            $reservedStock = (float) (
                                $product->inventory
                                    ?->reserved_quantity ?? 0
                            );

                            $availableStock = max(
                                0,
                                $currentStock - $reservedStock
                            );

                            $averageCost = (float) (
                                $product->inventory
                                    ?->average_cost ?? 0
                            );

                            $stockValue =
                                $currentStock * $averageCost;

                            $alertQuantity = (float) (
                                $product->alert_quantity
                            );
                        @endphp

                        <tr
                            wire:key="inventory-product-{{ $product->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                        >
                            {{-- Product --}}
                            <td class="px-4 py-4">
                                <p
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ $product->name }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    SKU: {{ $product->sku }}
                                </p>
                            </td>

                            {{-- Category --}}
                            <td
                                class="px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $product->category->name }}
                            </td>

                            {{-- Brand --}}
                            <td
                                class="px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $product->brand?->name
                                    ?? 'No brand' }}
                            </td>

                            {{-- Current --}}
                            <td
                                class="px-4 py-4 text-right font-bold text-gray-900 dark:text-white"
                            >
                                {{ number_format(
                                    $currentStock,
                                    3
                                ) }}

                                <span
                                    class="ml-1 text-xs font-normal text-gray-500"
                                >
                                    {{ $product->unit->short_name }}
                                </span>
                            </td>

                            {{-- Reserved --}}
                            <td
                                class="px-4 py-4 text-right text-amber-700 dark:text-amber-400"
                            >
                                {{ number_format(
                                    $reservedStock,
                                    3
                                ) }}
                            </td>

                            {{-- Available --}}
                            <td
                                class="px-4 py-4 text-right font-semibold text-blue-700 dark:text-blue-400"
                            >
                                {{ number_format(
                                    $availableStock,
                                    3
                                ) }}
                            </td>

                            {{-- Alert --}}
                            <td
                                class="px-4 py-4 text-right text-gray-600 dark:text-gray-300"
                            >
                                {{ number_format(
                                    $alertQuantity,
                                    3
                                ) }}
                            </td>

                            {{-- Average cost --}}
                            <td
                                class="px-4 py-4 text-right text-gray-700 dark:text-gray-300"
                            >
                                ৳{{ number_format(
                                    $averageCost,
                                    2
                                ) }}
                            </td>

                            {{-- Stock value --}}
                            <td
                                class="px-4 py-4 text-right font-semibold text-gray-900 dark:text-white"
                            >
                                ৳{{ number_format(
                                    $stockValue,
                                    2
                                ) }}
                            </td>

                            {{-- Stock status --}}
                            <td class="px-4 py-4">
                                @if ($currentStock <= 0)
                                    <span
                                        class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        Out of Stock
                                    </span>
                                @elseif (
                                    $currentStock <= $alertQuantity
                                )
                                    <span
                                        class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                                    >
                                        Low Stock
                                    </span>
                                @else
                                    <span
                                        class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        In Stock
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="10"
                                class="px-5 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                No inventory products found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($this->inventoryProducts->hasPages())
            <div
                class="border-t border-gray-200 p-4 dark:border-gray-700"
            >
                {{ $this->inventoryProducts->links() }}
            </div>
        @endif
    </div>
</div>