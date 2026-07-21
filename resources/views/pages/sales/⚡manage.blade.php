<?php

use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $paymentStatusFilter = '';

    public ?int $selectedSaleId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->paymentStatusFilter = '';

        $this->resetPage();

        unset($this->sales);
    }

    /**
     * Sale details modal খুলবে।
     */
    public function viewSale(int $saleId): void
    {
        $this->selectedSaleId = $saleId;

        unset($this->selectedSale);
    }

    /**
     * Sale details modal বন্ধ করবে।
     */
    public function closeSale(): void
    {
        $this->selectedSaleId = null;

        unset($this->selectedSale);
    }

    /**
     * Draft sale complete করে inventory stock কমাবে।
     */
    public function completeSale(int $saleId): void
    {
        $sale = Sale::query()->findOrFail($saleId);

        try {
            app(SaleService::class)->complete(
                $sale,
                auth()->user()
            );

            $this->workflowCompleted(
                'Sale completed successfully and inventory stock updated.'
            );
        } catch (ValidationException $exception) {
            $this->showWorkflowError($exception);
        }
    }

    /**
     * Draft sale cancel করবে।
     */
    public function cancelSale(int $saleId): void
    {
        $sale = Sale::query()->findOrFail($saleId);

        try {
            app(SaleService::class)->cancel(
                $sale,
                auth()->user()
            );

            $this->workflowCompleted(
                'Sale cancelled successfully.'
            );
        } catch (ValidationException $exception) {
            $this->showWorkflowError($exception);
        }
    }

    private function workflowCompleted(string $message): void
    {
        unset($this->sales);
        unset($this->summary);
        unset($this->selectedSale);

        $this->resetErrorBag('sale');

        session()->flash('success', $message);
    }

    private function showWorkflowError(
        ValidationException $exception
    ): void {
        $message = collect($exception->errors())
            ->flatten()
            ->first();

        $this->addError(
            'sale',
            $message ?? 'Unable to complete this action.'
        );
    }

    /**
     * Sales dashboard summary।
     *
     * @return array<string, int|float>
     */
    #[Computed]
    public function summary(): array
    {
        return [
            'total_sales' => Sale::query()->count(),

            'draft_sales' => Sale::query()
                ->where('status', Sale::STATUS_DRAFT)
                ->count(),

            'completed_sales' => Sale::query()
                ->where('status', Sale::STATUS_COMPLETED)
                ->count(),

            'cancelled_sales' => Sale::query()
                ->where('status', Sale::STATUS_CANCELLED)
                ->count(),

            'completed_amount' => (float) Sale::query()
                ->where('status', Sale::STATUS_COMPLETED)
                ->sum('grand_total'),

            'due_amount' => (float) Sale::query()
                ->where('status', Sale::STATUS_COMPLETED)
                ->sum('due_amount'),
        ];
    }

    /**
     * Filter করা sales list।
     */
    #[Computed]
    public function sales()
    {
        $search = trim($this->search);

        return Sale::query()
            ->with([
                'customer:id,name,phone,email',
                'createdBy:id,name,email',
            ])
            ->withCount('items')
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'sale_number',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhereHas(
                                    'customer',
                                    function ($query) use ($search): void {
                                        $query
                                            ->where(
                                                'name',
                                                'like',
                                                '%'.$search.'%'
                                            )
                                            ->orWhere(
                                                'phone',
                                                'like',
                                                '%'.$search.'%'
                                            )
                                            ->orWhere(
                                                'email',
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
                $this->statusFilter !== '',
                fn ($query) => $query->where(
                    'status',
                    $this->statusFilter
                )
            )
            ->when(
                $this->paymentStatusFilter !== '',
                fn ($query) => $query->where(
                    'payment_status',
                    $this->paymentStatusFilter
                )
            )
            ->latest('sale_date')
            ->latest('id')
            ->paginate(10);
    }

    /**
     * Selected sale-এর complete details।
     */
    #[Computed]
    public function selectedSale(): ?Sale
    {
        if ($this->selectedSaleId === null) {
            return null;
        }

        return Sale::query()
            ->with([
                'customer',
                'createdBy:id,name,email',
                'items.product:id,name,sku,unit_id',
                'items.product.unit:id,name,short_name',
            ])
            ->findOrFail($this->selectedSaleId);
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
                Sales Management
            </h1>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                View, complete and manage customer sales.
            </p>
        </div>

        @can('create sales')
            <a
                href="{{ route('sales.index') }}"
                wire:navigate
                class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
                + Create Sale
            </a>
        @endcan
    </div>

    {{-- Success message --}}
    @if (session()->has('success'))
        <div
            class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
        >
            {{ session('success') }}
        </div>
    @endif

    {{-- Workflow error --}}
    @error('sale')
        <div
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
        >
            {{ $message }}
        </div>
    @enderror

    {{-- Summary cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Total Sales
            </p>

            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">
                {{ number_format($this->summary['total_sales']) }}
            </p>
        </div>

        <div
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Draft Sales
            </p>

            <p class="mt-3 text-3xl font-bold text-gray-700 dark:text-gray-300">
                {{ number_format($this->summary['draft_sales']) }}
            </p>
        </div>

        <div
            class="rounded-xl border border-green-200 bg-green-50 p-5 shadow-sm dark:border-green-900 dark:bg-green-950/40"
        >
            <p class="text-sm font-medium text-green-700 dark:text-green-300">
                Completed
            </p>

            <p class="mt-3 text-3xl font-bold text-green-700 dark:text-green-300">
                {{ number_format($this->summary['completed_sales']) }}
            </p>
        </div>

        <div
            class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-900 dark:bg-red-950/40"
        >
            <p class="text-sm font-medium text-red-700 dark:text-red-300">
                Cancelled
            </p>

            <p class="mt-3 text-3xl font-bold text-red-700 dark:text-red-300">
                {{ number_format($this->summary['cancelled_sales']) }}
            </p>
        </div>

        <div
            class="rounded-xl border border-blue-200 bg-blue-50 p-5 shadow-sm dark:border-blue-900 dark:bg-blue-950/40"
        >
            <p class="text-sm font-medium text-blue-700 dark:text-blue-300">
                Sales Amount
            </p>

            <p class="mt-3 text-2xl font-bold text-blue-700 dark:text-blue-300">
                ৳{{ number_format(
                    $this->summary['completed_amount'],
                    2
                ) }}
            </p>
        </div>

        <div
            class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/40"
        >
            <p class="text-sm font-medium text-amber-700 dark:text-amber-300">
                Total Due
            </p>

            <p class="mt-3 text-2xl font-bold text-amber-700 dark:text-amber-300">
                ৳{{ number_format(
                    $this->summary['due_amount'],
                    2
                ) }}
            </p>
        </div>
    </div>

    {{-- Filters --}}
    <div
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label
                    for="search"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Search Sale
                </label>

                <input
                    id="search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Sale number, customer or phone..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </div>

            <div>
                <label
                    for="statusFilter"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Sale Status
                </label>

                <select
                    id="statusFilter"
                    wire:model.live="statusFilter"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div>
                <label
                    for="paymentStatusFilter"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Payment Status
                </label>

                <select
                    id="paymentStatusFilter"
                    wire:model.live="paymentStatusFilter"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">All payments</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                </select>
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

    {{-- Sales table --}}
    <div
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="border-b border-gray-200 p-5 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Sales List
            </h2>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                All sales and their current status.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1250px] w-full text-left text-sm">
                <thead
                    class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                >
                    <tr>
                        <th class="px-4 py-3">Sale</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Paid</th>
                        <th class="px-4 py-3 text-right">Due</th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Created By</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody
                    class="divide-y divide-gray-200 dark:divide-gray-700"
                >
                    @forelse ($this->sales as $sale)
                        <tr
                            wire:key="sale-{{ $sale->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                        >
                            {{-- Sale number --}}
                            <td class="px-4 py-4">
                                <p
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ $sale->sale_number }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    ID: #{{ $sale->id }}
                                </p>
                            </td>

                            {{-- Customer --}}
                            <td class="px-4 py-4">
                                @if ($sale->customer)
                                    <p
                                        class="font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ $sale->customer->name }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $sale->customer->phone
                                            ?? $sale->customer->email
                                            ?? 'No contact' }}
                                    </p>
                                @else
                                    <p
                                        class="font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        Walk-in Customer
                                    </p>
                                @endif
                            </td>

                            {{-- Date --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $sale->sale_date->format('d M Y') }}
                            </td>

                            {{-- Items --}}
                            <td
                                class="px-4 py-4 text-center font-semibold text-gray-700 dark:text-gray-300"
                            >
                                {{ $sale->items_count }}
                            </td>

                            {{-- Total --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-right font-semibold text-gray-900 dark:text-white"
                            >
                                ৳{{ number_format(
                                    (float) $sale->grand_total,
                                    2
                                ) }}
                            </td>

                            {{-- Paid --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-right font-medium text-green-700 dark:text-green-400"
                            >
                                ৳{{ number_format(
                                    (float) $sale->paid_amount,
                                    2
                                ) }}
                            </td>

                            {{-- Due --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-right font-medium text-red-600"
                            >
                                ৳{{ number_format(
                                    (float) $sale->due_amount,
                                    2
                                ) }}
                            </td>

                            {{-- Payment status --}}
                            <td class="px-4 py-4">
                                @if (
                                    $sale->payment_status
                                    === Sale::PAYMENT_PAID
                                )
                                    <span
                                        class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        Paid
                                    </span>
                                @elseif (
                                    $sale->payment_status
                                    === Sale::PAYMENT_PARTIAL
                                )
                                    <span
                                        class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                                    >
                                        Partial
                                    </span>
                                @else
                                    <span
                                        class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        Unpaid
                                    </span>
                                @endif
                            </td>

                            {{-- Sale status --}}
                            <td class="px-4 py-4">
                                @if ($sale->status === Sale::STATUS_COMPLETED)
                                    <span
                                        class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        Completed
                                    </span>
                                @elseif (
                                    $sale->status
                                    === Sale::STATUS_CANCELLED
                                )
                                    <span
                                        class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        Cancelled
                                    </span>
                                @else
                                    <span
                                        class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    >
                                        Draft
                                    </span>
                                @endif
                            </td>

                            {{-- Created by --}}
                            <td
                                class="px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $sale->createdBy->name }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-4">
                                <div
                                    class="flex flex-wrap justify-end gap-2"
                                >
                                    <button
                                        type="button"
                                        wire:click="viewSale({{ $sale->id }})"
                                        class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        View
                                    </button>

                                    @if (
                                        $sale->status
                                        === Sale::STATUS_DRAFT
                                    )
                                        @can('create sales')
                                            <button
                                                type="button"
                                                wire:click="completeSale({{ $sale->id }})"
                                                wire:confirm="Complete this sale and reduce inventory stock?"
                                                wire:loading.attr="disabled"
                                                wire:target="completeSale({{ $sale->id }})"
                                                class="rounded-lg bg-green-50 px-3 py-2 text-xs font-semibold text-green-700 transition hover:bg-green-100 disabled:opacity-50 dark:bg-green-950 dark:text-green-300"
                                            >
                                                Complete
                                            </button>
                                        @endcan

                                        @can('delete sales')
                                            <button
                                                type="button"
                                                wire:click="cancelSale({{ $sale->id }})"
                                                wire:confirm="Are you sure you want to cancel this draft sale?"
                                                wire:loading.attr="disabled"
                                                wire:target="cancelSale({{ $sale->id }})"
                                                class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-100 disabled:opacity-50 dark:bg-red-950 dark:text-red-300"
                                            >
                                                Cancel
                                            </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="11"
                                class="px-5 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                No sales found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->sales->hasPages())
            <div
                class="border-t border-gray-200 p-4 dark:border-gray-700"
            >
                {{ $this->sales->links() }}
            </div>
        @endif
    </div>

    {{-- Sale details modal --}}
    @php
        $selectedSale = $this->selectedSale;
    @endphp

    @if ($selectedSale)
        <div
            wire:click.self="closeSale"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        >
            <div
                class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white shadow-2xl dark:bg-gray-900"
            >
                {{-- Modal heading --}}
                <div
                    class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900"
                >
                    <div>
                        <h2
                            class="text-xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ $selectedSale->sale_number }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Sale details and product information
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeSale"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300"
                    >
                        Close
                    </button>
                </div>

                <div class="space-y-6 p-6">
                    {{-- General information --}}
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <p class="text-xs uppercase text-gray-500">
                                Customer
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedSale->customer?->name
                                    ?? 'Walk-in Customer' }}
                            </p>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <p class="text-xs uppercase text-gray-500">
                                Sale Date
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedSale->sale_date
                                    ->format('d M Y') }}
                            </p>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <p class="text-xs uppercase text-gray-500">
                                Created By
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedSale->createdBy->name }}
                            </p>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <p class="text-xs uppercase text-gray-500">
                                Status
                            </p>

                            <p
                                class="mt-2 font-semibold capitalize text-gray-900 dark:text-white"
                            >
                                {{ $selectedSale->status }}
                            </p>
                        </div>
                    </div>

                    {{-- Product items --}}
                    <div
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="border-b border-gray-200 p-4 dark:border-gray-700"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Sold Products
                            </h3>
                        </div>

                        <div class="overflow-x-auto">
                            <table
                                class="min-w-[850px] w-full text-left text-sm"
                            >
                                <thead
                                    class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    <tr>
                                        <th class="px-4 py-3">Product</th>
                                        <th class="px-4 py-3 text-right">
                                            Quantity
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Returned
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Unit Price
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Discount
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Tax
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Total
                                        </th>
                                    </tr>
                                </thead>

                                <tbody
                                    class="divide-y divide-gray-200 dark:divide-gray-700"
                                >
                                    @foreach (
                                        $selectedSale->items
                                        as $item
                                    )
                                        <tr>
                                            <td class="px-4 py-4">
                                                <p
                                                    class="font-medium text-gray-900 dark:text-white"
                                                >
                                                    {{ $item->product->name }}
                                                </p>

                                                <p
                                                    class="mt-1 text-xs text-gray-500"
                                                >
                                                    SKU:
                                                    {{ $item->product->sku }}
                                                </p>
                                            </td>

                                            <td
                                                class="px-4 py-4 text-right text-gray-700 dark:text-gray-300"
                                            >
                                                {{ number_format(
                                                    (float) $item->quantity,
                                                    3
                                                ) }}

                                                {{ $item->product->unit
                                                    ?->short_name }}
                                            </td>

                                            <td
                                                class="px-4 py-4 text-right text-amber-700 dark:text-amber-400"
                                            >
                                                {{ number_format(
                                                    (float) $item
                                                        ->returned_quantity,
                                                    3
                                                ) }}
                                            </td>

                                            <td
                                                class="px-4 py-4 text-right"
                                            >
                                                ৳{{ number_format(
                                                    (float) $item->unit_price,
                                                    2
                                                ) }}
                                            </td>

                                            <td
                                                class="px-4 py-4 text-right text-red-600"
                                            >
                                                - ৳{{ number_format(
                                                    (float) $item
                                                        ->discount_amount,
                                                    2
                                                ) }}
                                            </td>

                                            <td
                                                class="px-4 py-4 text-right"
                                            >
                                                ৳{{ number_format(
                                                    (float) $item->tax_amount,
                                                    2
                                                ) }}
                                            </td>

                                            <td
                                                class="px-4 py-4 text-right font-semibold text-gray-900 dark:text-white"
                                            >
                                                ৳{{ number_format(
                                                    (float) $item->line_total,
                                                    2
                                                ) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Sale summary --}}
                    <div class="ml-auto max-w-md space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">
                                Subtotal
                            </span>

                            <span class="font-medium">
                                ৳{{ number_format(
                                    (float) $selectedSale->subtotal,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">
                                Discount
                            </span>

                            <span class="font-medium text-red-600">
                                - ৳{{ number_format(
                                    (float) $selectedSale
                                        ->discount_amount,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">
                                Tax
                            </span>

                            <span class="font-medium">
                                ৳{{ number_format(
                                    (float) $selectedSale->tax_amount,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">
                                Shipping
                            </span>

                            <span class="font-medium">
                                ৳{{ number_format(
                                    (float) $selectedSale
                                        ->shipping_amount,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div
                            class="flex justify-between border-t border-gray-200 pt-3 text-lg font-bold dark:border-gray-700"
                        >
                            <span>Grand Total</span>

                            <span class="text-blue-600">
                                ৳{{ number_format(
                                    (float) $selectedSale->grand_total,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div class="flex justify-between text-green-700">
                            <span>Paid Amount</span>

                            <span class="font-semibold">
                                ৳{{ number_format(
                                    (float) $selectedSale->paid_amount,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div class="flex justify-between text-red-600">
                            <span>Due Amount</span>

                            <span class="font-semibold">
                                ৳{{ number_format(
                                    (float) $selectedSale->due_amount,
                                    2
                                ) }}
                            </span>
                        </div>
                    </div>

                    @if ($selectedSale->notes)
                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800"
                        >
                            <p
                                class="text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                Notes
                            </p>

                            <p
                                class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300"
                            >
                                {{ $selectedSale->notes }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>