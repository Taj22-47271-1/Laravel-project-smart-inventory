<?php

use App\Models\StockMovement;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $movementTypeFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    /**
     * Search পরিবর্তন হলে pagination reset করবে।
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Movement type পরিবর্তন হলে pagination reset করবে।
     */
    public function updatedMovementTypeFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Starting date পরিবর্তন হলে pagination reset করবে।
     */
    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    /**
     * Ending date পরিবর্তন হলে pagination reset করবে।
     */
    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    /**
     * সব filter reset করবে।
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->movementTypeFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';

        $this->resetPage();
    }

    /**
     * Stock movement summary।
     *
     * @return array<string, int|float>
     */
    #[Computed]
    public function summary(): array
    {
        $increaseTypes = [
            StockMovement::TYPE_PURCHASE,
            StockMovement::TYPE_SALE_RETURN,
            StockMovement::TYPE_ADJUSTMENT_IN,
        ];

        $decreaseTypes = [
            StockMovement::TYPE_SALE,
            StockMovement::TYPE_PURCHASE_RETURN,
            StockMovement::TYPE_ADJUSTMENT_OUT,
        ];

        return [
            'total_movements' => StockMovement::query()->count(),

            'total_stock_in' => (float) StockMovement::query()
                ->whereIn('movement_type', $increaseTypes)
                ->sum('quantity'),

            'total_stock_out' => (float) StockMovement::query()
                ->whereIn('movement_type', $decreaseTypes)
                ->sum('quantity'),

            'purchase_movements' => StockMovement::query()
                ->where(
                    'movement_type',
                    StockMovement::TYPE_PURCHASE
                )
                ->count(),
        ];
    }

    /**
     * Filter করা Stock Movement list।
     */
    #[Computed]
    public function movements()
    {
        $search = trim($this->search);

        return StockMovement::query()
            ->with([
                'product:id,name,sku,unit_id',
                'product.unit:id,name,short_name',
                'createdBy:id,name,email',
                'reference',
            ])
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'notes',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'reference_id',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhereHas(
                                    'product',
                                    function ($query) use ($search): void {
                                        $query
                                            ->where(
                                                'name',
                                                'like',
                                                '%'.$search.'%'
                                            )
                                            ->orWhere(
                                                'sku',
                                                'like',
                                                '%'.$search.'%'
                                            );
                                    }
                                )
                                ->orWhereHas(
                                    'createdBy',
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
                $this->movementTypeFilter !== '',
                fn ($query) => $query->where(
                    'movement_type',
                    $this->movementTypeFilter
                )
            )
            ->when(
                $this->dateFrom !== '',
                fn ($query) => $query->whereDate(
                    'created_at',
                    '>=',
                    $this->dateFrom
                )
            )
            ->when(
                $this->dateTo !== '',
                fn ($query) => $query->whereDate(
                    'created_at',
                    '<=',
                    $this->dateTo
                )
            )
            ->latest()
            ->paginate(15);
    }

    /**
     * Movement type অনুযায়ী readable label।
     */
    public function movementLabel(string $movementType): string
    {
        return match ($movementType) {
            StockMovement::TYPE_PURCHASE => 'Purchase',
            StockMovement::TYPE_SALE => 'Sale',
            StockMovement::TYPE_PURCHASE_RETURN =>
                'Purchase Return',
            StockMovement::TYPE_SALE_RETURN =>
                'Sale Return',
            StockMovement::TYPE_ADJUSTMENT_IN =>
                'Adjustment In',
            StockMovement::TYPE_ADJUSTMENT_OUT =>
                'Adjustment Out',
            default => ucfirst(
                str_replace('_', ' ', $movementType)
            ),
        };
    }

    /**
     * Movement stock বাড়ায় কি না।
     */
    public function isStockIncrease(
        string $movementType
    ): bool {
        return in_array($movementType, [
            StockMovement::TYPE_PURCHASE,
            StockMovement::TYPE_SALE_RETURN,
            StockMovement::TYPE_ADJUSTMENT_IN,
        ], true);
    }

    /**
     * Reference-এর readable text।
     */
    public function referenceText(
        StockMovement $movement
    ): string {
        if (! $movement->reference) {
            return $movement->reference_id
                ? '#'.$movement->reference_id
                : 'Manual entry';
        }

        if (
            isset($movement->reference->purchase_number)
        ) {
            return $movement->reference->purchase_number;
        }

        if (
            isset($movement->reference->sale_number)
        ) {
            return $movement->reference->sale_number;
        }

        return class_basename(
            $movement->reference_type
        ).' #'.$movement->reference_id;
    }
};

?>

<div class="space-y-6">
    {{-- Page heading --}}
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Stock Movement History
            </h1>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Track every stock increase and decrease.
            </p>
        </div>

        <a
            href="{{ route('inventory.index') }}"
            wire:navigate
            class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
        >
            Back to Inventory
        </a>
    </div>

    {{-- Summary cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Total Movements
            </p>

            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">
                {{ number_format(
                    $this->summary['total_movements']
                ) }}
            </p>
        </div>

        <div
            class="rounded-xl border border-green-200 bg-green-50 p-5 shadow-sm dark:border-green-900 dark:bg-green-950/40"
        >
            <p class="text-sm font-medium text-green-700 dark:text-green-300">
                Total Stock In
            </p>

            <p class="mt-3 text-3xl font-bold text-green-700 dark:text-green-300">
                +{{ number_format(
                    $this->summary['total_stock_in'],
                    3
                ) }}
            </p>
        </div>

        <div
            class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-900 dark:bg-red-950/40"
        >
            <p class="text-sm font-medium text-red-700 dark:text-red-300">
                Total Stock Out
            </p>

            <p class="mt-3 text-3xl font-bold text-red-700 dark:text-red-300">
                -{{ number_format(
                    $this->summary['total_stock_out'],
                    3
                ) }}
            </p>
        </div>

        <div
            class="rounded-xl border border-blue-200 bg-blue-50 p-5 shadow-sm dark:border-blue-900 dark:bg-blue-950/40"
        >
            <p class="text-sm font-medium text-blue-700 dark:text-blue-300">
                Purchase Receives
            </p>

            <p class="mt-3 text-3xl font-bold text-blue-700 dark:text-blue-300">
                {{ number_format(
                    $this->summary['purchase_movements']
                ) }}
            </p>
        </div>
    </div>

    {{-- Filters --}}
    <div
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            {{-- Search --}}
            <div class="xl:col-span-2">
                <label
                    for="search"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Search
                </label>

                <input
                    id="search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Product, SKU, user or notes..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </div>

            {{-- Movement type --}}
            <div>
                <label
                    for="movementTypeFilter"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Movement Type
                </label>

                <select
                    id="movementTypeFilter"
                    wire:model.live="movementTypeFilter"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">All movements</option>
                    <option value="purchase">Purchase</option>
                    <option value="sale">Sale</option>
                    <option value="purchase_return">
                        Purchase Return
                    </option>
                    <option value="sale_return">
                        Sale Return
                    </option>
                    <option value="adjustment_in">
                        Adjustment In
                    </option>
                    <option value="adjustment_out">
                        Adjustment Out
                    </option>
                </select>
            </div>

            {{-- Date from --}}
            <div>
                <label
                    for="dateFrom"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Date From
                </label>

                <input
                    id="dateFrom"
                    type="date"
                    wire:model.live="dateFrom"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </div>

            {{-- Date to --}}
            <div>
                <label
                    for="dateTo"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Date To
                </label>

                <input
                    id="dateTo"
                    type="date"
                    wire:model.live="dateTo"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </div>
        </div>

        <button
            type="button"
            wire:click="resetFilters"
            class="mt-4 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
        >
            Reset Filters
        </button>
    </div>

    {{-- Movement table --}}
    <div
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="border-b border-gray-200 p-5 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Movement Records
            </h2>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Complete inventory transaction history.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1250px] w-full text-left text-sm">
                <thead
                    class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                >
                    <tr>
                        <th class="px-4 py-3">Date & Time</th>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Movement</th>
                        <th class="px-4 py-3 text-right">Quantity</th>
                        <th class="px-4 py-3 text-right">
                            Stock Before
                        </th>
                        <th class="px-4 py-3 text-right">
                            Stock After
                        </th>
                        <th class="px-4 py-3 text-right">Unit Cost</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Created By</th>
                        <th class="px-4 py-3">Notes</th>
                    </tr>
                </thead>

                <tbody
                    class="divide-y divide-gray-200 dark:divide-gray-700"
                >
                    @forelse ($this->movements as $movement)
                        @php
                            $stockIncrease =
                                $this->isStockIncrease(
                                    $movement->movement_type
                                );
                        @endphp

                        <tr
                            wire:key="stock-movement-{{ $movement->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                        >
                            {{-- Date --}}
                            <td class="whitespace-nowrap px-4 py-4">
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ $movement->created_at
                                        ->format('d M Y') }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $movement->created_at
                                        ->format('h:i A') }}
                                </p>
                            </td>

                            {{-- Product --}}
                            <td class="px-4 py-4">
                                <p
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ $movement->product->name }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    SKU: {{ $movement->product->sku }}
                                </p>
                            </td>

                            {{-- Movement type --}}
                            <td class="px-4 py-4">
                                @if ($stockIncrease)
                                    <span
                                        class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        {{ $this->movementLabel(
                                            $movement->movement_type
                                        ) }}
                                    </span>
                                @else
                                    <span
                                        class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        {{ $this->movementLabel(
                                            $movement->movement_type
                                        ) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Quantity --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-right font-bold"
                            >
                                <span
                                    class="{{ $stockIncrease
                                        ? 'text-green-600'
                                        : 'text-red-600' }}"
                                >
                                    {{ $stockIncrease ? '+' : '-' }}
                                    {{ number_format(
                                        (float) $movement->quantity,
                                        3
                                    ) }}
                                </span>

                                <span
                                    class="ml-1 text-xs font-normal text-gray-500"
                                >
                                    {{ $movement->product->unit
                                        ?->short_name }}
                                </span>
                            </td>

                            {{-- Before --}}
                            <td
                                class="px-4 py-4 text-right text-gray-600 dark:text-gray-300"
                            >
                                {{ number_format(
                                    (float) $movement->stock_before,
                                    3
                                ) }}
                            </td>

                            {{-- After --}}
                            <td
                                class="px-4 py-4 text-right font-semibold text-gray-900 dark:text-white"
                            >
                                {{ number_format(
                                    (float) $movement->stock_after,
                                    3
                                ) }}
                            </td>

                            {{-- Unit cost --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-right text-gray-700 dark:text-gray-300"
                            >
                                @if ($movement->unit_cost !== null)
                                    ৳{{ number_format(
                                        (float) $movement->unit_cost,
                                        2
                                    ) }}
                                @else
                                    N/A
                                @endif
                            </td>

                            {{-- Reference --}}
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex rounded-md bg-gray-100 px-2.5 py-1 font-mono text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {{ $this->referenceText(
                                        $movement
                                    ) }}
                                </span>
                            </td>

                            {{-- Created by --}}
                            <td
                                class="px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $movement->createdBy?->name
                                    ?? 'System' }}
                            </td>

                            {{-- Notes --}}
                            <td
                                class="max-w-xs px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                <p class="line-clamp-2">
                                    {{ $movement->notes ?? 'No notes' }}
                                </p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="10"
                                class="px-5 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                No stock movements found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($this->movements->hasPages())
            <div
                class="border-t border-gray-200 p-4 dark:border-gray-700"
            >
                {{ $this->movements->links() }}
            </div>
        @endif
    </div>
</div>