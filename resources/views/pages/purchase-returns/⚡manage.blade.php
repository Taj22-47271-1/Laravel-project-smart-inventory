<?php

use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public ?int $selectedId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();

        unset($this->returns);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();

        unset($this->returns);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';

        $this->resetPage();

        unset($this->returns);
    }

    /**
     * Purchase return details modal খুলবে।
     */
    public function openDetails(int $id): void
    {
        $this->selectedId = $id;

        unset($this->selectedReturn);
    }

    /**
     * Purchase return details modal বন্ধ করবে।
     */
    public function closeDetails(): void
    {
        $this->selectedId = null;

        unset($this->selectedReturn);
    }

    /**
     * Draft purchase return complete করে stock কমাবে।
     */
    public function completeReturn(int $id): void
    {
        try {
            app(PurchaseReturnService::class)->complete(
                PurchaseReturn::query()->findOrFail($id),
                auth()->user()
            );

            $this->workflowCompleted(
                'Purchase return completed successfully. Inventory stock has been reduced.'
            );
        } catch (ValidationException $exception) {
            $this->showValidationError($exception);
        } catch (AuthorizationException $exception) {
            $this->addError(
                'workflow',
                $exception->getMessage()
            );
        }
    }

    /**
     * Draft purchase return cancel করবে।
     */
    public function cancelReturn(int $id): void
    {
        try {
            app(PurchaseReturnService::class)->cancel(
                PurchaseReturn::query()->findOrFail($id),
                auth()->user()
            );

            $this->workflowCompleted(
                'Purchase return cancelled successfully.'
            );
        } catch (ValidationException $exception) {
            $this->showValidationError($exception);
        } catch (AuthorizationException $exception) {
            $this->addError(
                'workflow',
                $exception->getMessage()
            );
        }
    }

    private function workflowCompleted(string $message): void
    {
        $this->selectedId = null;

        unset($this->returns);
        unset($this->selectedReturn);

        $this->resetErrorBag('workflow');

        session()->flash('success', $message);
    }

    private function showValidationError(
        ValidationException $exception
    ): void {
        $message = collect($exception->errors())
            ->flatten()
            ->first();

        $this->addError(
            'workflow',
            $message ?? 'Unable to complete this action.'
        );
    }

    /**
     * Filter করা purchase return list।
     */
    #[Computed]
    public function returns()
    {
        $search = trim($this->search);

        return PurchaseReturn::query()
            ->with([
                'purchase.supplier:id,name,company_name,supplier_code',
                'createdBy:id,name,email',
                'completedBy:id,name,email',
            ])
            ->withCount('items')
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'return_number',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhereHas(
                                    'purchase',
                                    fn ($query) => $query->where(
                                        'purchase_number',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                )
                                ->orWhereHas(
                                    'purchase.supplier',
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
                $this->status !== '',
                fn ($query) => $query->where(
                    'status',
                    $this->status
                )
            )
            ->latest('return_date')
            ->latest('id')
            ->paginate(10);
    }

    /**
     * Selected purchase return details।
     */
    #[Computed]
    public function selectedReturn(): ?PurchaseReturn
    {
        if ($this->selectedId === null) {
            return null;
        }

        return PurchaseReturn::query()
            ->with([
                'purchase.supplier',
                'items.product.unit',
                'createdBy:id,name,email',
                'completedBy:id,name,email',
            ])
            ->findOrFail($this->selectedId);
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
                Purchase Returns
            </h1>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Manage draft, completed and cancelled supplier returns.
            </p>
        </div>

        @can('create purchase returns')
            <a
                href="{{ route('purchase-returns.index') }}"
                wire:navigate
                class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
                + Create Return
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
    @error('workflow')
        <div
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
        >
            {{ $message }}
        </div>
    @enderror

    {{-- Filters --}}
    <div
        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="grid gap-4 md:grid-cols-2">
            <div>
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
                    placeholder="Return, purchase or supplier..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </div>

            <div>
                <label
                    for="status"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Return Status
                </label>

                <select
                    id="status"
                    wire:model.live="status"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
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

    {{-- Purchase return table --}}
    <div
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="border-b border-gray-200 p-5 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Purchase Return List
            </h2>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                All supplier return records and workflow statuses.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1150px] w-full text-left text-sm">
                <thead
                    class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                >
                    <tr>
                        <th class="px-4 py-3">Return</th>
                        <th class="px-4 py-3">Purchase</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Completed By</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody
                    class="divide-y divide-gray-200 dark:divide-gray-700"
                >
                    @forelse ($this->returns as $return)
                        <tr
                            wire:key="purchase-return-{{ $return->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                        >
                            {{-- Return number --}}
                            <td class="px-4 py-4">
                                <p
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ $return->return_number }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Created by
                                    {{ $return->createdBy->name }}
                                </p>
                            </td>

                            {{-- Purchase --}}
                            <td class="px-4 py-4">
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ $return->purchase->purchase_number }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Purchase ID:
                                    #{{ $return->purchase->id }}
                                </p>
                            </td>

                            {{-- Supplier --}}
                            <td class="px-4 py-4">
                                <p
                                    class="font-medium text-gray-800 dark:text-gray-200"
                                >
                                    {{ $return->purchase->supplier
                                        ->company_name
                                        ?? $return->purchase->supplier->name }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $return->purchase->supplier
                                        ->supplier_code }}
                                </p>
                            </td>

                            {{-- Date --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $return->return_date->format('d M Y') }}
                            </td>

                            {{-- Items --}}
                            <td
                                class="px-4 py-4 text-center font-semibold text-gray-700 dark:text-gray-300"
                            >
                                {{ $return->items_count }}
                            </td>

                            {{-- Total --}}
                            <td
                                class="whitespace-nowrap px-4 py-4 text-right font-semibold text-gray-900 dark:text-white"
                            >
                                ৳{{ number_format(
                                    (float) $return->total_amount,
                                    2
                                ) }}
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-4">
                                @if (
                                    $return->status
                                    === PurchaseReturn::STATUS_COMPLETED
                                )
                                    <span
                                        class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        Completed
                                    </span>
                                @elseif (
                                    $return->status
                                    === PurchaseReturn::STATUS_CANCELLED
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

                            {{-- Completed by --}}
                            <td
                                class="px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $return->completedBy?->name
                                    ?? 'Not completed' }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-4">
                                <div
                                    class="flex flex-wrap justify-end gap-2"
                                >
                                    <button
                                        type="button"
                                        wire:click="openDetails({{ $return->id }})"
                                        class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        View
                                    </button>

                                    @if ($return->isDraft())
                                        @can('complete purchase returns')
                                            <button
                                                type="button"
                                                wire:click="completeReturn({{ $return->id }})"
                                                wire:confirm="Complete this return and reduce inventory stock?"
                                                wire:loading.attr="disabled"
                                                wire:target="completeReturn({{ $return->id }})"
                                                class="rounded-lg bg-green-50 px-3 py-2 text-xs font-semibold text-green-700 transition hover:bg-green-100 disabled:opacity-50 dark:bg-green-950 dark:text-green-300"
                                            >
                                                Complete
                                            </button>
                                        @endcan

                                        @can('cancel purchase returns')
                                            <button
                                                type="button"
                                                wire:click="cancelReturn({{ $return->id }})"
                                                wire:confirm="Are you sure you want to cancel this purchase return?"
                                                wire:loading.attr="disabled"
                                                wire:target="cancelReturn({{ $return->id }})"
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
                                colspan="9"
                                class="px-5 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                No purchase returns found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->returns->hasPages())
            <div
                class="border-t border-gray-200 p-4 dark:border-gray-700"
            >
                {{ $this->returns->links() }}
            </div>
        @endif
    </div>

    {{-- Selected return details --}}
    @php
        $selectedReturn = $this->selectedReturn;
    @endphp

    @if ($selectedReturn)
        <div
            wire:click.self="closeDetails"
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
                            {{ $selectedReturn->return_number }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Purchase:
                            {{ $selectedReturn->purchase->purchase_number }}
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="closeDetails"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Close
                    </button>
                </div>

                <div class="space-y-6 p-6">
                    {{-- General information --}}
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800"
                        >
                            <p class="text-xs uppercase text-gray-500">
                                Supplier
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedReturn->purchase->supplier
                                    ->company_name
                                    ?? $selectedReturn->purchase->supplier
                                        ->name }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800"
                        >
                            <p class="text-xs uppercase text-gray-500">
                                Return Date
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedReturn->return_date
                                    ->format('d M Y') }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800"
                        >
                            <p class="text-xs uppercase text-gray-500">
                                Created By
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedReturn->createdBy->name }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800"
                        >
                            <p class="text-xs uppercase text-gray-500">
                                Completed By
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedReturn->completedBy?->name
                                    ?? 'Not completed' }}
                            </p>
                        </div>
                    </div>

                    {{-- Returned products --}}
                    <div
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="border-b border-gray-200 p-4 dark:border-gray-700"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Returned Products
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
                                            Unit Cost
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Discount
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Tax
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Line Total
                                        </th>
                                    </tr>
                                </thead>

                                <tbody
                                    class="divide-y divide-gray-200 dark:divide-gray-700"
                                >
                                    @foreach ($selectedReturn->items as $item)
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
                                                class="px-4 py-4 text-right"
                                            >
                                                ৳{{ number_format(
                                                    (float) $item->unit_cost,
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

                    {{-- Summary --}}
                    <div class="ml-auto max-w-md space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">
                                Subtotal
                            </span>

                            <span class="font-medium">
                                ৳{{ number_format(
                                    (float) $selectedReturn->subtotal,
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
                                    (float) $selectedReturn
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
                                    (float) $selectedReturn->tax_amount,
                                    2
                                ) }}
                            </span>
                        </div>

                        <div
                            class="flex justify-between border-t border-gray-200 pt-3 text-lg font-bold dark:border-gray-700"
                        >
                            <span>Total Amount</span>

                            <span class="text-blue-600">
                                ৳{{ number_format(
                                    (float) $selectedReturn->total_amount,
                                    2
                                ) }}
                            </span>
                        </div>
                    </div>

                    @if ($selectedReturn->reason)
                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800"
                        >
                            <p
                                class="text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                Return Reason
                            </p>

                            <p
                                class="mt-2 text-sm text-gray-600 dark:text-gray-300"
                            >
                                {{ $selectedReturn->reason }}
                            </p>
                        </div>
                    @endif

                    @if ($selectedReturn->notes)
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
                                {{ $selectedReturn->notes }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>