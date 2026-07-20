<?php

use App\Models\SaleReturn;
use App\Services\SaleReturnService;
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

    public function openDetails(int $id): void
    {
        $this->selectedId = $id;

        unset($this->selectedReturn);
    }

    public function closeDetails(): void
    {
        $this->selectedId = null;

        unset($this->selectedReturn);
    }

    public function completeReturn(int $id): void
    {
        try {
            app(SaleReturnService::class)->complete(
                SaleReturn::query()->findOrFail($id),
                auth()->user()
            );

            $this->workflowCompleted(
                'Sale return completed successfully. Returned stock has been restored.'
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

    public function cancelReturn(int $id): void
    {
        try {
            app(SaleReturnService::class)->cancel(
                SaleReturn::query()->findOrFail($id),
                auth()->user()
            );

            $this->workflowCompleted(
                'Sale return cancelled successfully.'
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

    #[Computed]
    public function returns()
    {
        $search = trim($this->search);

        return SaleReturn::query()
            ->with([
                'sale.customer:id,name,phone,email',
                'createdBy:id,name',
                'completedBy:id,name',
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
                                    'sale',
                                    fn ($query) => $query->where(
                                        'sale_number',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                )
                                ->orWhereHas(
                                    'sale.customer',
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

    #[Computed]
    public function selectedReturn(): ?SaleReturn
    {
        if ($this->selectedId === null) {
            return null;
        }

        return SaleReturn::query()
            ->with([
                'sale.customer',
                'items.product.unit',
                'createdBy:id,name,email',
                'completedBy:id,name,email',
            ])
            ->findOrFail($this->selectedId);
    }
};

?>

<div class="space-y-6">
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Sale Returns
            </h1>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Manage draft, completed and cancelled sale returns.
            </p>
        </div>

        @can('create sale returns')
            <a
                href="{{ route('sale-returns.index') }}"
                wire:navigate
                class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
                + Create Return
            </a>
        @endcan
    </div>

    @if (session()->has('success'))
        <div
            class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
        >
            {{ session('success') }}
        </div>
    @endif

    @error('workflow')
        <div
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
        >
            {{ $message }}
        </div>
    @enderror

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
                    placeholder="Return number, sale number or customer..."
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

    <div
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="border-b border-gray-200 p-5 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Return List
            </h2>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                All sale return records and their workflow status.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1100px] w-full text-left text-sm">
                <thead
                    class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                >
                    <tr>
                        <th class="px-4 py-3">Return</th>
                        <th class="px-4 py-3">Sale</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Refund</th>
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
                            wire:key="sale-return-{{ $return->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                        >
                            <td class="px-4 py-4">
                                <p
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ $return->return_number }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Created by {{ $return->createdBy->name }}
                                </p>
                            </td>

                            <td
                                class="px-4 py-4 font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{ $return->sale->sale_number }}
                            </td>

                            <td class="px-4 py-4">
                                <p
                                    class="font-medium text-gray-800 dark:text-gray-200"
                                >
                                    {{ $return->sale->customer?->name
                                        ?? 'Walk-in Customer' }}
                                </p>

                                @if ($return->sale->customer)
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $return->sale->customer->phone
                                            ?? $return->sale->customer->email
                                            ?? 'No contact' }}
                                    </p>
                                @endif
                            </td>

                            <td
                                class="whitespace-nowrap px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $return->return_date->format('d M Y') }}
                            </td>

                            <td
                                class="px-4 py-4 text-center font-semibold text-gray-700 dark:text-gray-300"
                            >
                                {{ $return->items_count }}
                            </td>

                            <td
                                class="whitespace-nowrap px-4 py-4 text-right font-semibold text-gray-900 dark:text-white"
                            >
                                ৳{{ number_format(
                                    (float) $return->refund_amount,
                                    2
                                ) }}
                            </td>

                            <td class="px-4 py-4">
                                @if (
                                    $return->status
                                    === SaleReturn::STATUS_COMPLETED
                                )
                                    <span
                                        class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        Completed
                                    </span>
                                @elseif (
                                    $return->status
                                    === SaleReturn::STATUS_CANCELLED
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

                            <td
                                class="px-4 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $return->completedBy?->name
                                    ?? 'Not completed' }}
                            </td>

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
                                        @can('complete sale returns')
                                            <button
                                                type="button"
                                                wire:click="completeReturn({{ $return->id }})"
                                                wire:confirm="Complete this return and restore the returned stock?"
                                                wire:loading.attr="disabled"
                                                wire:target="completeReturn({{ $return->id }})"
                                                class="rounded-lg bg-green-50 px-3 py-2 text-xs font-semibold text-green-700 transition hover:bg-green-100 disabled:opacity-50 dark:bg-green-950 dark:text-green-300"
                                            >
                                                Complete
                                            </button>
                                        @endcan

                                        @can('cancel sale returns')
                                            <button
                                                type="button"
                                                wire:click="cancelReturn({{ $return->id }})"
                                                wire:confirm="Are you sure you want to cancel this return?"
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
                                No sale returns found.
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
                            Sale: {{ $selectedReturn->sale->sale_number }}
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
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800"
                        >
                            <p class="text-xs uppercase text-gray-500">
                                Customer
                            </p>

                            <p
                                class="mt-2 font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $selectedReturn->sale->customer?->name
                                    ?? 'Walk-in Customer' }}
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
                                            Unit Price
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Discount
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Tax
                                        </th>
                                        <th class="px-4 py-3 text-right">
                                            Refund
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
                                                    (float) $item
                                                        ->refund_amount,
                                                    2
                                                ) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

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
                            <span>Refund Amount</span>

                            <span class="text-blue-600">
                                ৳{{ number_format(
                                    (float) $selectedReturn->refund_amount,
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