<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $supplier_id = '';

    public string $supplier_invoice_number = '';

    public string $purchase_date = '';

    public string $expected_delivery_date = '';

    public string $discount_amount = '0.00';

    public string $tax_amount = '0.00';

    public string $shipping_amount = '0.00';

    public string $paid_amount = '0.00';

    public string $notes = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $items = [];

    /**
     * Component load হওয়ার সময় default data বসাবে।
     */
    public function mount(): void
    {
        $this->purchase_date = now()->format('Y-m-d');

        $this->addItem();
    }

    /**
     * নতুন product row যোগ করবে।
     */
    public function addItem(): void
    {
        $this->items[] = [
            'key' => (string) Str::uuid(),
            'product_id' => '',
            'quantity' => '1',
            'unit_cost' => '0.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
        ];
    }

    /**
     * Product row remove করবে।
     */
    public function removeItem(int $index): void
    {
        if (count($this->items) <= 1) {
            return;
        }

        unset($this->items[$index]);

        $this->items = array_values($this->items);

        $this->resetValidation();
    }

    /**
     * Purchase draft save করবে।
     */
    public function save(): void
    {
        abort_unless(
            auth()->user()?->can('create purchases'),
            403
        );

        $validated = $this->validate([
            'supplier_id' => [
                'required',
                'integer',
                'exists:suppliers,id',
            ],

            'supplier_invoice_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'purchase_date' => [
                'required',
                'date',
            ],

            'expected_delivery_date' => [
                'nullable',
                'date',
                'after_or_equal:purchase_date',
            ],

            'discount_amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'tax_amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'shipping_amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'paid_amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_cost' => [
                'required',
                'numeric',
                'min:0',
            ],

            'items.*.discount_amount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'items.*.tax_amount' => [
                'required',
                'numeric',
                'min:0',
            ],
        ], [
            'items.*.product_id.required' =>
                'Please select a product.',

            'items.*.product_id.distinct' =>
                'The same product cannot be added twice.',

            'items.*.quantity.gt' =>
                'Quantity must be greater than zero.',
        ]);

        /*
         * প্রতিটি product row validate করবে।
         */
        foreach ($validated['items'] as $index => $item) {
            $baseAmount =
                (float) $item['quantity'] *
                (float) $item['unit_cost'];

            $itemTax = (float) $item['tax_amount'];

            $itemDiscount =
                (float) $item['discount_amount'];

            if ($itemDiscount > ($baseAmount + $itemTax)) {
                throw ValidationException::withMessages([
                    "items.{$index}.discount_amount" =>
                        'Item discount cannot exceed the item amount.',
                ]);
            }
        }

        $subtotal = $this->subtotal;
        $grandTotal = $this->grandTotal;
        $paidAmount = (float) $validated['paid_amount'];

        if ($paidAmount > $grandTotal) {
            throw ValidationException::withMessages([
                'paid_amount' =>
                    'Paid amount cannot exceed the grand total.',
            ]);
        }

        $purchaseNumber = $this->generatePurchaseNumber();

        DB::transaction(function () use (
            $validated,
            $subtotal,
            $grandTotal,
            $paidAmount,
            $purchaseNumber
        ): void {
            $dueAmount = max(
                0,
                $grandTotal - $paidAmount
            );

            $purchase = Purchase::create([
                'purchase_number' => $purchaseNumber,

                'supplier_invoice_number' =>
                    filled(
                        $validated['supplier_invoice_number']
                        ?? null
                    )
                        ? trim(
                            $validated[
                                'supplier_invoice_number'
                            ]
                        )
                        : null,

                'supplier_id' =>
                    (int) $validated['supplier_id'],

                'purchase_date' =>
                    $validated['purchase_date'],

                'expected_delivery_date' =>
                    filled(
                        $validated[
                            'expected_delivery_date'
                        ] ?? null
                    )
                        ? $validated[
                            'expected_delivery_date'
                        ]
                        : null,

                'subtotal' => $subtotal,

                'discount_amount' =>
                    (float) $validated['discount_amount'],

                'tax_amount' =>
                    (float) $validated['tax_amount'],

                'shipping_amount' =>
                    (float) $validated['shipping_amount'],

                'grand_total' => $grandTotal,

                'paid_amount' => $paidAmount,

                'due_amount' => $dueAmount,

                'status' => Purchase::STATUS_DRAFT,

                'payment_status' =>
                    $this->determinePaymentStatus(
                        $paidAmount,
                        $grandTotal
                    ),

                'notes' => filled(
                    $validated['notes'] ?? null
                )
                    ? trim($validated['notes'])
                    : null,

                'created_by' => (int) auth()->id(),

                'approved_by' => null,

                'approved_at' => null,
            ]);

            $purchaseItems = collect(
                $validated['items']
            )->map(function (array $item): array {
                $quantity = (float) $item['quantity'];
                $unitCost = (float) $item['unit_cost'];

                $discount =
                    (float) $item['discount_amount'];

                $tax = (float) $item['tax_amount'];

                $lineTotal = max(
                    0,
                    ($quantity * $unitCost)
                    - $discount
                    + $tax
                );

                return [
                    'product_id' =>
                        (int) $item['product_id'],

                    'quantity' => $quantity,

                    'received_quantity' => 0,

                    'unit_cost' => $unitCost,

                    'discount_amount' => $discount,

                    'tax_amount' => $tax,

                    'line_total' => $lineTotal,
                ];
            })->all();

            $purchase->items()->createMany(
                $purchaseItems
            );
        });

        $this->resetForm();

        session()->flash(
            'success',
            "Purchase {$purchaseNumber} saved as draft."
        );
    }

    /**
     * Product rows-এর subtotal।
     */
    #[Computed]
    public function subtotal(): float
    {
        return collect($this->items)
            ->sum(function (array $item): float {
                $quantity = $this->number(
                    $item['quantity'] ?? 0
                );

                $unitCost = $this->number(
                    $item['unit_cost'] ?? 0
                );

                $discount = $this->number(
                    $item['discount_amount'] ?? 0
                );

                $tax = $this->number(
                    $item['tax_amount'] ?? 0
                );

                return max(
                    0,
                    ($quantity * $unitCost)
                    - $discount
                    + $tax
                );
            });
    }

    /**
     * Purchase-এর grand total।
     */
    #[Computed]
    public function grandTotal(): float
    {
        return max(
            0,
            $this->subtotal
            - $this->number($this->discount_amount)
            + $this->number($this->tax_amount)
            + $this->number($this->shipping_amount)
        );
    }

    /**
     * Purchase-এর due amount।
     */
    #[Computed]
    public function dueAmount(): float
    {
        return max(
            0,
            $this->grandTotal
            - $this->number($this->paid_amount)
        );
    }

    /**
     * Active supplier dropdown data।
     */
    #[Computed]
    public function suppliers()
    {
        return Supplier::query()
            ->where('status', true)
            ->orderBy('name')
            ->get([
                'id',
                'supplier_code',
                'name',
                'company_name',
            ]);
    }

    /**
     * Active product dropdown data।
     */
    #[Computed]
    public function products()
    {
        return Product::query()
            ->where('status', true)
            ->with([
                'unit:id,name,short_name',
            ])
            ->orderBy('name')
            ->get([
                'id',
                'unit_id',
                'name',
                'sku',
                'purchase_price',
            ]);
    }

    /**
     * Unique purchase number তৈরি করবে।
     */
    private function generatePurchaseNumber(): string
    {
        do {
            $number = 'PUR-'
                .now()->format('Ymd-His')
                .'-'
                .Str::upper(Str::random(4));
        } while (
            Purchase::withTrashed()
                ->where('purchase_number', $number)
                ->exists()
        );

        return $number;
    }

    /**
     * Payment status নির্ধারণ করবে।
     */
    private function determinePaymentStatus(
        float $paidAmount,
        float $grandTotal
    ): string {
        if ($paidAmount <= 0) {
            return Purchase::PAYMENT_UNPAID;
        }

        if ($paidAmount >= $grandTotal) {
            return Purchase::PAYMENT_PAID;
        }

        return Purchase::PAYMENT_PARTIAL;
    }

    /**
     * Numeric value safely convert করবে।
     */
    private function number(mixed $value): float
    {
        return is_numeric($value)
            ? (float) $value
            : 0;
    }

    /**
     * Purchase form reset করবে।
     */
    private function resetForm(): void
    {
        $this->supplier_id = '';
        $this->supplier_invoice_number = '';
        $this->purchase_date =
            now()->format('Y-m-d');
        $this->expected_delivery_date = '';
        $this->discount_amount = '0.00';
        $this->tax_amount = '0.00';
        $this->shipping_amount = '0.00';
        $this->paid_amount = '0.00';
        $this->notes = '';
        $this->items = [];

        $this->addItem();

        $this->resetValidation();
    }
};

?>

<div class="space-y-6">
    {{-- Page heading --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Purchase Management
        </h1>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Create purchase drafts with multiple products.
        </p>
    </div>

    {{-- Success message --}}
    @if (session()->has('success'))
        <div
            class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
        >
            {{ session('success') }}
        </div>
    @endif

    @can('create purchases')
        <form wire:submit="save" class="space-y-6">
            {{-- General information --}}
            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Purchase Information
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Select the supplier and purchase dates.
                    </p>
                </div>

                <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    {{-- Supplier --}}
                    <div>
                        <label
                            for="supplier_id"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Supplier
                        </label>

                        <select
                            id="supplier_id"
                            wire:model="supplier_id"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">Select supplier</option>

                            @foreach ($this->suppliers as $supplier)
                                <option value="{{ $supplier->id }}">
                                    {{ $supplier->supplier_code }}
                                    —
                                    {{ $supplier->company_name
                                        ?? $supplier->name }}
                                </option>
                            @endforeach
                        </select>

                        @error('supplier_id')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Supplier invoice --}}
                    <div>
                        <label
                            for="supplier_invoice_number"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Supplier Invoice
                        </label>

                        <input
                            id="supplier_invoice_number"
                            type="text"
                            wire:model="supplier_invoice_number"
                            placeholder="Optional invoice number"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >

                        @error('supplier_invoice_number')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Purchase date --}}
                    <div>
                        <label
                            for="purchase_date"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Purchase Date
                        </label>

                        <input
                            id="purchase_date"
                            type="date"
                            wire:model="purchase_date"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >

                        @error('purchase_date')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Expected delivery --}}
                    <div>
                        <label
                            for="expected_delivery_date"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Expected Delivery
                        </label>

                        <input
                            id="expected_delivery_date"
                            type="date"
                            wire:model="expected_delivery_date"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >

                        @error('expected_delivery_date')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Purchase products --}}
            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div
                    class="flex flex-col gap-3 border-b border-gray-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Purchase Products
                        </h2>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Add one or more products.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="addItem"
                        class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                    >
                        + Add Product
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1050px] w-full text-left text-sm">
                        <thead
                            class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                        >
                            <tr>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">Quantity</th>
                                <th class="px-4 py-3">Unit Cost</th>
                                <th class="px-4 py-3">Discount</th>
                                <th class="px-4 py-3">Tax</th>
                                <th class="px-4 py-3 text-right">
                                    Line Total
                                </th>
                                <th class="px-4 py-3 text-right">
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($items as $index => $item)
                                @php
                                    $quantity = is_numeric(
                                        $item['quantity'] ?? null
                                    )
                                        ? (float) $item['quantity']
                                        : 0;

                                    $unitCost = is_numeric(
                                        $item['unit_cost'] ?? null
                                    )
                                        ? (float) $item['unit_cost']
                                        : 0;

                                    $itemDiscount = is_numeric(
                                        $item['discount_amount'] ?? null
                                    )
                                        ? (float) $item['discount_amount']
                                        : 0;

                                    $itemTax = is_numeric(
                                        $item['tax_amount'] ?? null
                                    )
                                        ? (float) $item['tax_amount']
                                        : 0;

                                    $lineTotal = max(
                                        0,
                                        ($quantity * $unitCost)
                                        - $itemDiscount
                                        + $itemTax
                                    );
                                @endphp

                                <tr wire:key="purchase-item-{{ $item['key'] }}">
                                    {{-- Product --}}
                                    <td class="min-w-72 px-4 py-4">
                                        <select
                                            wire:model="items.{{ $index }}.product_id"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >
                                            <option value="">
                                                Select product
                                            </option>

                                            @foreach ($this->products as $product)
                                                <option value="{{ $product->id }}">
                                                    {{ $product->name }}
                                                    —
                                                    {{ $product->sku }}
                                                    (৳{{ number_format(
                                                        (float) $product->purchase_price,
                                                        2
                                                    ) }})
                                                </option>
                                            @endforeach
                                        </select>

                                        @error("items.{$index}.product_id")
                                            <p class="mt-1 text-xs text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>

                                    {{-- Quantity --}}
                                    <td class="w-32 px-4 py-4">
                                        <input
                                            type="number"
                                            min="0.001"
                                            step="0.001"
                                            wire:model.live.debounce.300ms="items.{{ $index }}.quantity"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >

                                        @error("items.{$index}.quantity")
                                            <p class="mt-1 text-xs text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>

                                    {{-- Unit cost --}}
                                    <td class="w-40 px-4 py-4">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            wire:model.live.debounce.300ms="items.{{ $index }}.unit_cost"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >

                                        @error("items.{$index}.unit_cost")
                                            <p class="mt-1 text-xs text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>

                                    {{-- Item discount --}}
                                    <td class="w-36 px-4 py-4">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            wire:model.live.debounce.300ms="items.{{ $index }}.discount_amount"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >

                                        @error("items.{$index}.discount_amount")
                                            <p class="mt-1 text-xs text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>

                                    {{-- Item tax --}}
                                    <td class="w-36 px-4 py-4">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            wire:model.live.debounce.300ms="items.{{ $index }}.tax_amount"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >

                                        @error("items.{$index}.tax_amount")
                                            <p class="mt-1 text-xs text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>

                                    {{-- Total --}}
                                    <td
                                        class="whitespace-nowrap px-4 py-4 text-right font-semibold text-gray-900 dark:text-white"
                                    >
                                        ৳{{ number_format($lineTotal, 2) }}
                                    </td>

                                    {{-- Remove --}}
                                    <td class="px-4 py-4 text-right">
                                        <button
                                            type="button"
                                            wire:click="removeItem({{ $index }})"
                                            @disabled(count($items) <= 1)
                                            class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-red-950 dark:text-red-300"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @error('items')
                    <p class="px-5 pb-4 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_420px]">
                {{-- Notes --}}
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <label
                        for="notes"
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Purchase Notes
                    </label>

                    <textarea
                        id="notes"
                        wire:model="notes"
                        rows="8"
                        placeholder="Optional purchase notes..."
                        class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    ></textarea>

                    @error('notes')
                        <p class="mt-1.5 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Purchase summary --}}
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Purchase Summary
                    </h2>

                    <div class="mt-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                Items Subtotal
                            </span>

                            <span class="font-semibold text-gray-900 dark:text-white">
                                ৳{{ number_format($this->subtotal, 2) }}
                            </span>
                        </div>

                        {{-- Purchase discount --}}
                        <div>
                            <label
                                for="discount_amount"
                                class="mb-1.5 block text-sm text-gray-600 dark:text-gray-400"
                            >
                                Purchase Discount
                            </label>

                            <input
                                id="discount_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live.debounce.300ms="discount_amount"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >

                            @error('discount_amount')
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Purchase tax --}}
                        <div>
                            <label
                                for="tax_amount"
                                class="mb-1.5 block text-sm text-gray-600 dark:text-gray-400"
                            >
                                Additional Tax
                            </label>

                            <input
                                id="tax_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live.debounce.300ms="tax_amount"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >

                            @error('tax_amount')
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Shipping --}}
                        <div>
                            <label
                                for="shipping_amount"
                                class="mb-1.5 block text-sm text-gray-600 dark:text-gray-400"
                            >
                                Shipping Cost
                            </label>

                            <input
                                id="shipping_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live.debounce.300ms="shipping_amount"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >

                            @error('shipping_amount')
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700"
                        >
                            <span class="font-semibold text-gray-900 dark:text-white">
                                Grand Total
                            </span>

                            <span class="text-xl font-bold text-gray-900 dark:text-white">
                                ৳{{ number_format($this->grandTotal, 2) }}
                            </span>
                        </div>

                        {{-- Paid amount --}}
                        <div>
                            <label
                                for="paid_amount"
                                class="mb-1.5 block text-sm text-gray-600 dark:text-gray-400"
                            >
                                Paid Amount
                            </label>

                            <input
                                id="paid_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live.debounce.300ms="paid_amount"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >

                            @error('paid_amount')
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-red-600">
                                Due Amount
                            </span>

                            <span class="text-lg font-bold text-red-600">
                                ৳{{ number_format($this->dueAmount, 2) }}
                            </span>
                        </div>
                    </div>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="mt-6 w-full rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="save">
                            Save Purchase Draft
                        </span>

                        <span wire:loading wire:target="save">
                            Saving Purchase...
                        </span>
                    </button>
                </div>
            </div>
        </form>
    @endcan
</div>