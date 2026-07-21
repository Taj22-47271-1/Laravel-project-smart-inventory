<?php

use App\Models\Purchase;
use App\Services\PurchaseService;
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

    public ?int $selectedPurchaseId = null;

    /**
     * Search পরিবর্তন হলে প্রথম page-এ যাবে।
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Status filter পরিবর্তন হলে প্রথম page-এ যাবে।
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Payment filter পরিবর্তন হলে প্রথম page-এ যাবে।
     */
    public function updatedPaymentStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Purchase details modal খুলবে।
     */
    public function viewPurchase(int $purchaseId): void
    {
        $this->selectedPurchaseId = $purchaseId;

        unset($this->selectedPurchase);
    }

    /**
     * Purchase details modal বন্ধ করবে।
     */
    public function closePurchase(): void
    {
        $this->selectedPurchaseId = null;

        unset($this->selectedPurchase);
    }

    /**
     * Draft purchase approval-এর জন্য submit করবে।
     */
    public function submitForApproval(int $purchaseId): void
    {
        $purchase = Purchase::findOrFail($purchaseId);

        try {
            app(PurchaseService::class)->submitForApproval(
                $purchase,
                auth()->user()
            );

            $this->workflowCompleted(
                'Purchase submitted for approval successfully.'
            );
        } catch (ValidationException $exception) {
            $this->showWorkflowError($exception);
        }
    }

    /**
     * Pending purchase approve করবে।
     */
    public function approvePurchase(int $purchaseId): void
    {
        $purchase = Purchase::findOrFail($purchaseId);

        try {
            app(PurchaseService::class)->approve(
                $purchase,
                auth()->user()
            );

            $this->workflowCompleted(
                'Purchase approved successfully.'
            );
        } catch (ValidationException $exception) {
            $this->showWorkflowError($exception);
        }
    }

    /**
     * Approved purchase receive করে stock বাড়াবে।
     */
    public function receivePurchase(int $purchaseId): void
    {
        $purchase = Purchase::findOrFail($purchaseId);

        try {
            app(PurchaseService::class)->receive(
                $purchase,
                auth()->user()
            );

            $this->workflowCompleted(
                'Purchase received and inventory updated successfully.'
            );
        } catch (ValidationException $exception) {
            $this->showWorkflowError($exception);
        }
    }

    /**
     * Draft অথবা pending purchase cancel করবে।
     */
    public function cancelPurchase(int $purchaseId): void
    {
        $purchase = Purchase::findOrFail($purchaseId);

        try {
            app(PurchaseService::class)->cancel(
                $purchase,
                auth()->user()
            );

            $this->workflowCompleted(
                'Purchase cancelled successfully.'
            );
        } catch (ValidationException $exception) {
            $this->showWorkflowError($exception);
        }
    }

    /**
     * Workflow action সফল হলে page data refresh করবে।
     */
    private function workflowCompleted(string $message): void
    {
        unset($this->purchases);
        unset($this->selectedPurchase);

        $this->resetErrorBag('purchase');

        session()->flash('success', $message);
    }

    /**
     * Workflow validation error দেখাবে।
     */
    private function showWorkflowError(
        ValidationException $exception
    ): void {
        $message = collect($exception->errors())
            ->flatten()
            ->first();

        $this->addError(
            'purchase',
            $message ?? 'Unable to complete this action.'
        );
    }

    /**
     * Filter করা purchase list।
     */
    #[Computed]
    public function purchases()
    {
        $search = trim($this->search);

        return Purchase::query()
            ->with([
                'supplier:id,supplier_code,name,company_name',
                'createdBy:id,name',
                'approvedBy:id,name',
            ])
            ->withCount('items')
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'purchase_number',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'supplier_invoice_number',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhereHas(
                                    'supplier',
                                    function ($query) use ($search): void {
                                        $query
                                            ->where(
                                                'name',
                                                'like',
                                                '%'.$search.'%'
                                            )
                                            ->orWhere(
                                                'company_name',
                                                'like',
                                                '%'.$search.'%'
                                            )
                                            ->orWhere(
                                                'supplier_code',
                                                'like',
                                                '%'.$search.'%'
                                            );
                                    }
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
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(10);
    }

    /**
     * Selected purchase-এর complete details।
     */
    #[Computed]
    public function selectedPurchase(): ?Purchase
    {
        if ($this->selectedPurchaseId === null) {
            return null;
        }

        return Purchase::query()
            ->with([
                'supplier',
                'createdBy:id,name,email',
                'approvedBy:id,name,email',
                'items.product:id,name,sku,unit_id',
                'items.product.unit:id,name,short_name',
            ])
            ->findOrFail($this->selectedPurchaseId);
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
                Purchase Workflow
            </h1>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Submit, approve, receive and manage purchases.
            </p>
        </div>

        @can('create purchases')
            <a
                href="{{ route('purchases.index') }}"
                wire:navigate
                class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
                + Create Purchase
            </a>
        @endcan
    </div>

    {{-- Success message --}}
    @if (session()->has('success'))
        <div
            class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
        >
            {{ session('success') }}
        </div>
    @endif

    {{-- Workflow error --}}
    @error('purchase')
        <div
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
        >
            {{ $message }}
        </div>
    @enderror

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
                    Search Purchase
                </label>

                <input
                    id="search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Purchase number, invoice or supplier..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </div>

            <div>
                <label
                    for="statusFilter"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Purchase Status
                </label>

                <select
                    id="statusFilter"
                    wire:model.live="statusFilter"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="received">Received</option>
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
    </div>

    {{-- Purchase list --}}
    <div
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="border-b border-gray-200 p-5 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Purchase List
            </h2>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                All purchase records and current workflow statuses.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1250px] w-full text-left text-sm">
                <thead
                    class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                >
                    <tr>
                        <th class="px-4 py-3">Purchase</th>
                        <th class="px-4 py-3">Supplier</th>
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
                    @forelse ($this->purchases as $purchase)
                        <tr
                            wire:key="purchase-{{ $purchase->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                        >
                            {{-- Purchase number --}}
                            <td class="px-4 py-4">
                                <p
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ $purchase->purchase_number }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Invoice:
                                    {{ $purchase->supplier_invoice_number
                                        ?? 'N/A' }}
                                </p>
                            </td>

                            {{-- Supplier --}}
                            <td class="px-4 py-4">
                                <p
                                    class="font-medium text-gray-800 dark:text-gray-200"
                                >
                                    {{ $purchase->supplier->company_name
                                        ?? $purchase->supplier->name }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $purchase->supplier->supplier_code }}
                                </p>
                            </td>

                            {{-- Date --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $purchase->purchase_date->format('d M Y') }}
                            </td>

                            {{-- Items --}}
                            <td
                                class="px-4 py-4 text-center font-semibold text-gray-700 dark:text-gray-300"
                            >
                                {{ $purchase->items_count }}
                            </td>

                            {{-- Total --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-right font-semibold text-gray-900 dark:text-white"
                            >
                                ৳{{ number_format(
                                    (float) $purchase->grand_total,
                                    2
                                ) }}
                            </td>

                            {{-- Paid --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-right text-green-700 dark:text-green-400"
                            >
                                ৳{{ number_format(
                                    (float) $purchase->paid_amount,
                                    2
                                ) }}
                            </td>

                            {{-- Due --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-right font-medium text-red-600"
                            >
                                ৳{{ number_format(
                                    (float) $purchase->due_amount,
                                    2
                                ) }}
                            </td>

                            {{-- Payment status --}}
                            <td class="px-4 py-4">
                                @if (
                                    $purchase->payment_status
                                    === Purchase::PAYMENT_PAID
                                )
                                    <span
                                        class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        Paid
                                    </span>
                                @elseif (
                                    $purchase->payment_status
                                    === Purchase::PAYMENT_PARTIAL
                                )
                                    <span
                                        class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                                    >
                                        Partial
                                    </span>
                                @else
                                    <span
                                        class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        Unpaid
                                    </span>
                                @endif
                            </td>

                            {{-- Purchase status --}}
                            <td class="px-4 py-4">
                                @switch($purchase->status)
                                    @case(Purchase::STATUS_DRAFT)
                                        <span
                                            class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                        >
                                            Draft
                                        </span>
                                        @break

                                    @case(Purchase::STATUS_PENDING)
                                        <span
                                            class="inline-flex rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-medium text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300"
                                        >
                                            Pending
                                        </span>
                                        @break

                                    @case(Purchase::STATUS_APPROVED)
                                        <span
                                            class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                                        >
                                            Approved
                                        </span>
                                        @break

                                    @case(Purchase::STATUS_RECEIVED)
                                        <span
                                            class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                        >
                                            Received
                                        </span>
                                        @break

                                    @case(Purchase::STATUS_CANCELLED)
                                        <span
                                            class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                        >
                                            Cancelled
                                        </span>
                                        @break
                                @endswitch
                            </td>

                            {{-- Created by --}}
                            <td
                                class="px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $purchase->createdBy->name }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-4">
                                <div
                                    class="flex flex-wrap justify-end gap-2"
                                >
                                    <button
                                        type="button"
                                        wire:click="viewPurchase({{ $purchase->id }})"
                                        class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        View
                                    </button>

                                    @if (
                                        $purchase->status
                                        === Purchase::STATUS_DRAFT
                                    )
                                        @can('create purchases')
                                            <button
                                                type="button"
                                                wire:click="submitForApproval({{ $purchase->id }})"
                                                wire:confirm="Submit this purchase for approval?"
                                                wire:loading.attr="disabled"
                                                wire:target="submitForApproval({{ $purchase->id }})"
                                                class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100 disabled:opacity-50 dark:bg-blue-950 dark:text-blue-300"
                                            >
                                                Submit
                                            </button>
                                        @endcan
                                    @endif

                                    @if (
                                        $purchase->status
                                        === Purchase::STATUS_PENDING
                                    )
                                        @can('approve purchases')
                                            <button
                                                type="button"
                                                wire:click="approvePurchase({{ $purchase->id }})"
                                                wire:confirm="Approve this purchase?"
                                                wire:loading.attr="disabled"
                                                wire:target="approvePurchase({{ $purchase->id }})"
                                                class="rounded-lg bg-green-50 px-3 py-2 text-xs font-semibold text-green-700 transition hover:bg-green-100 disabled:opacity-50 dark:bg-green-950 dark:text-green-300"
                                            >
                                                Approve
                                            </button>
                                        @endcan
                                    @endif

                                    @if (
                                        $purchase->status
                                        === Purchase::STATUS_APPROVED
                                    )
                                        @if (
                                            auth()->user()->can(
                                                'approve purchases'
                                            ) ||
                                            auth()->user()->can(
                                                'manage inventory'
                                            )
                                        )
                                            <button
                                                type="button"
                                                wire:click="receivePurchase({{ $purchase->id }})"
                                                wire:confirm="Receive this purchase and add all remaining quantities to inventory?"
                                                wire:loading.attr="disabled"
                                                wire:target="receivePurchase({{ $purchase->id }})"
                                                class="rounded-lg bg-purple-50 px-3 py-2 text-xs font-semibold text-purple-700 transition hover:bg-purple-100 disabled:opacity-50 dark:bg-purple-950 dark:text-purple-300"
                                            >
                                                Receive
                                            </button>
                                        @endif
                                    @endif

                                    @if (
                                        in_array(
                                            $purchase->status,
                                            [
                                                Purchase::STATUS_DRAFT,
                                                Purchase::STATUS_PENDING,
                                            ],
                                            true
                                        )
                                    )
                                        @can('delete purchases')
                                            <button
                                                type="button"
                                                wire:click="cancelPurchase({{ $purchase->id }})"
                                                wire:confirm="Are you sure you want to cancel this purchase?"
                                                wire:loading.attr="disabled"
                                                wire:target="cancelPurchase({{ $purchase->id }})"
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
                                No purchases found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->purchases->hasPages())
            <div
                class="border-t border-gray-200 p-4 dark:border-gray-700"
            >
                {{ $this->purchases->links() }}
            </div>
        @endif
    </div>

    {{-- Purchase details modal --}}
    @php
        $selectedPurchase = $this->selectedPurchase;
    @endphp

    @if ($selectedPurchase)
        <div
            wire:click.self="closePurchase"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        >
            <div
                class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white shadow-2xl dark:bg-gray-900"
            >
                {{-- Modal heading --}}
                <div
                    class="sticky top-0 flex items-center justify-between gap-4 border-b border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900"
                >
                    <div>
                        <h2
                            class="text-xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ $selectedPurchase->purchase_number }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Purchase details and product information
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="closePurchase"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300"
                    >
                        Close
                    </button>
                </div>

                <div class="space-y-6 p-6">
                    {{-- General details --}}
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <p class="text-xs uppercase text-gray-500">
                                Supplier
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedPurchase->supplier->company_name
                                    ?? $selectedPurchase->supplier->name }}
                            </p>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <p class="text-xs uppercase text-gray-500">
                                Purchase Date
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedPurchase->purchase_date
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
                                {{ $selectedPurchase->createdBy->name }}
                            </p>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <p class="text-xs uppercase text-gray-500">
                                Approved By
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedPurchase->approvedBy?->name
                                    ?? 'Not approved' }}
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
                                Purchase Products
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
                                        <th class="px-4 py-3">Quantity</th>
                                        <th class="px-4 py-3">Received</th>
                                        <th class="px-4 py-3 text-right">
                                            Unit Cost
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
                                        $selectedPurchase->items
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
                                                    {{ $item->product->sku }}
                                                </p>
                                            </td>

                                            <td
                                                class="px-4 py-4 text-gray-700 dark:text-gray-300"
                                            >
                                                {{ number_format(
                                                    (float) $item->quantity,
                                                    3
                                                ) }}
                                                {{ $item->product->unit
                                                    ?->short_name }}
                                            </td>

                                            <td
                                                class="px-4 py-4 text-gray-700 dark:text-gray-300"
                                            >
                                                {{ number_format(
                                                    (float) $item
                                                        ->received_quantity,
                                                    3
                                                ) }}
                                            </td>

                                            <td
                                                class="px-4 py-4 text-right"
                                            >
                                                ৳{{ number_format(
                                                    (float) $item->unit_cost,
                                                    2
                                                ) }}
                                            </td>

                                            <td
                                                class="px-4 py-4 text-right"
                                            >
                                                ৳{{ number_format(
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

                    {{-- Summary --}}
                    <div class="ml-auto max-w-md space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">
                                Subtotal
                            </span>

                            <span class="font-medium">
                                ৳{{ number_format(
                                    (float) $selectedPurchase->subtotal,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">
                                Discount
                            </span>

                            <span class="font-medium">
                                - ৳{{ number_format(
                                    (float) $selectedPurchase
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
                                    (float) $selectedPurchase->tax_amount,
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
                                    (float) $selectedPurchase
                                        ->shipping_amount,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div
                            class="flex justify-between border-t border-gray-200 pt-3 text-lg font-bold dark:border-gray-700"
                        >
                            <span>Grand Total</span>

                            <span>
                                ৳{{ number_format(
                                    (float) $selectedPurchase->grand_total,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div class="flex justify-between text-green-700">
                            <span>Paid Amount</span>

                            <span class="font-semibold">
                                ৳{{ number_format(
                                    (float) $selectedPurchase->paid_amount,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div class="flex justify-between text-red-600">
                            <span>Due Amount</span>

                            <span class="font-semibold">
                                ৳{{ number_format(
                                    (float) $selectedPurchase->due_amount,
                                    2
                                ) }}
                            </span>
                        </div>
                    </div>

                    @if ($selectedPurchase->notes)
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
                                {{ $selectedPurchase->notes }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>