<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public $customerId = null;

    public string $saleDate = '';

    public string $shippingAmount = '0';

    public string $paidAmount = '0';

    public string $notes = '';

    public array $items = [];

    /**
     * Initial form data.
     */
    public function mount(): void
    {
        $this->saleDate = now()->format('Y-m-d');

        $this->items = [
            $this->newItem(),
        ];
    }

    /**
     * নতুন product row যোগ করবে।
     */
    public function addItem(): void
    {
        $this->items[] = $this->newItem();

        unset($this->totals);
    }

    /**
     * Product row remove করবে।
     */
    public function removeItem(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        if (count($this->items) === 1) {
            $this->items = [
                $this->newItem(),
            ];
        } else {
            unset($this->items[$index]);

            $this->items = array_values($this->items);
        }

        unset($this->totals);

        $this->resetValidation();
    }

    /**
     * Product select করলে selling price বসাবে।
     */
    public function updatedItems(
        mixed $value,
        string $key
    ): void {
        $segments = explode('.', $key);

        if (count($segments) < 2) {
            unset($this->totals);

            return;
        }

        $index = (int) $segments[0];
        $field = $segments[1];

        if (
            $field === 'product_id'
            && isset($this->items[$index])
        ) {
            $product = Product::query()
                ->where('status', true)
                ->find((int) $value);

            if ($product) {
                $this->items[$index]['unit_price'] =
                    (string) $product->selling_price;
            }
        }

        unset($this->totals);
    }

    public function updatedShippingAmount(): void
    {
        unset($this->totals);
    }

    public function updatedPaidAmount(): void
    {
        unset($this->totals);
    }

    /**
     * Customer options.
     */
    #[Computed]
    public function customers()
    {
        return Customer::query()
            ->orderBy('name')
            ->get();
    }

    /**
     * Active products with stock information.
     */
    #[Computed]
    public function products()
    {
        return Product::query()
            ->where('status', true)
            ->with([
                'unit:id,name,short_name',
                'inventory:id,product_id,quantity,reserved_quantity,average_cost',
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Form-এর live calculation.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function totals(): array
    {
        $subtotal = 0;
        $discountAmount = 0;
        $taxAmount = 0;
        $lineTotals = [];

        foreach ($this->items as $index => $item) {
            $quantity = max(
                0,
                (float) ($item['quantity'] ?? 0)
            );

            $unitPrice = max(
                0,
                (float) ($item['unit_price'] ?? 0)
            );

            $itemDiscount = max(
                0,
                (float) ($item['discount_amount'] ?? 0)
            );

            $itemTax = max(
                0,
                (float) ($item['tax_amount'] ?? 0)
            );

            $lineSubtotal = $quantity * $unitPrice;

            $lineTotal = max(
                0,
                $lineSubtotal - $itemDiscount + $itemTax
            );

            $subtotal += $lineSubtotal;
            $discountAmount += $itemDiscount;
            $taxAmount += $itemTax;

            $lineTotals[$index] = $lineTotal;
        }

        $shippingAmount = max(
            0,
            (float) $this->shippingAmount
        );

        $grandTotal = max(
            0,
            $subtotal
            - $discountAmount
            + $taxAmount
            + $shippingAmount
        );

        $paidAmount = max(
            0,
            (float) $this->paidAmount
        );

        $dueAmount = max(
            0,
            $grandTotal - $paidAmount
        );

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'shipping_amount' => $shippingAmount,
            'grand_total' => $grandTotal,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'payment_status' => $this->determinePaymentStatus(
                $grandTotal,
                $paidAmount
            ),
            'line_totals' => $lineTotals,
        ];
    }

    /**
     * Sale draft হিসেবে save করবে।
     */
    public function saveDraft(): void
    {
        $this->storeSale(false);
    }

    /**
     * Sale save করে সঙ্গে সঙ্গে complete করবে।
     */
    public function completeSale(): void
    {
        $this->storeSale(true);
    }

    /**
     * Sale এবং sale items database-এ save করবে।
     */
    private function storeSale(bool $complete): void
    {
        abort_unless(
            auth()->user()->can('create sales'),
            403
        );

        $validated = $this->validate(
            $this->rules(),
            $this->messages()
        );

        $preparedItems = $this->prepareItems(
            $validated['items'],
            $complete
        );

        $totals = $this->calculatePreparedTotals(
            $preparedItems
        );

        $paidAmount = (float) $validated['paidAmount'];

        if ($totals['grand_total'] <= 0) {
            throw ValidationException::withMessages([
                'sale' => 'Sale grand total must be greater than zero.',
            ]);
        }

        if ($paidAmount > $totals['grand_total']) {
            throw ValidationException::withMessages([
                'paidAmount' => 'Paid amount cannot exceed the grand total.',
            ]);
        }

        $user = auth()->user();

        $sale = DB::transaction(
            function () use (
                $validated,
                $preparedItems,
                $totals,
                $paidAmount,
                $complete,
                $user
            ): Sale {
                $sale = Sale::query()->create([
                    'sale_number' => $this->generateSaleNumber(),

                    'customer_id' => filled(
                        $validated['customerId'] ?? null
                    )
                        ? (int) $validated['customerId']
                        : null,

                    'sale_date' => $validated['saleDate'],

                    'subtotal' => $totals['subtotal'],

                    'discount_amount' =>
                        $totals['discount_amount'],

                    'tax_amount' => $totals['tax_amount'],

                    'shipping_amount' =>
                        $totals['shipping_amount'],

                    'grand_total' => $totals['grand_total'],

                    'paid_amount' => $paidAmount,

                    'due_amount' => max(
                        0,
                        $totals['grand_total'] - $paidAmount
                    ),

                    'status' => Sale::STATUS_DRAFT,

                    'payment_status' =>
                        $this->determinePaymentStatus(
                            $totals['grand_total'],
                            $paidAmount
                        ),

                    'notes' => filled(
                        $validated['notes'] ?? null
                    )
                        ? trim($validated['notes'])
                        : null,

                    'created_by' => $user->id,
                ]);

                $sale->items()->createMany(
                    $preparedItems
                );

                if ($complete) {
                    return app(SaleService::class)->complete(
                        $sale,
                        $user
                    );
                }

                return $sale->fresh([
                    'customer',
                    'items.product',
                    'createdBy',
                ]);
            },
            attempts: 3
        );

        session()->flash(
            'success',
            $complete
                ? 'Sale completed successfully. Stock has been updated. Sale number: '
                    .$sale->sale_number
                : 'Sale draft saved successfully. Sale number: '
                    .$sale->sale_number
        );

        $this->resetForm();
    }

    /**
     * Form validation rules.
     *
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'customerId' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],

            'saleDate' => [
                'required',
                'date',
            ],

            'shippingAmount' => [
                'required',
                'numeric',
                'min:0',
            ],

            'paidAmount' => [
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

            'items.*.unit_price' => [
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
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'customerId.exists' =>
                'The selected customer was not found.',

            'saleDate.required' =>
                'Sale date is required.',

            'items.required' =>
                'Add at least one product.',

            'items.min' =>
                'Add at least one product.',

            'items.*.product_id.required' =>
                'Select a product.',

            'items.*.product_id.distinct' =>
                'The same product cannot be added twice.',

            'items.*.product_id.exists' =>
                'The selected product was not found.',

            'items.*.quantity.required' =>
                'Quantity is required.',

            'items.*.quantity.gt' =>
                'Quantity must be greater than zero.',

            'items.*.unit_price.required' =>
                'Unit price is required.',

            'items.*.unit_price.min' =>
                'Unit price cannot be negative.',

            'items.*.discount_amount.min' =>
                'Discount cannot be negative.',

            'items.*.tax_amount.min' =>
                'Tax cannot be negative.',

            'shippingAmount.min' =>
                'Shipping amount cannot be negative.',

            'paidAmount.min' =>
                'Paid amount cannot be negative.',
        ];
    }

    /**
     * Product data যাচাই করে sale-item data প্রস্তুত করবে।
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, int|float>>
     */
    private function prepareItems(
        array $items,
        bool $complete
    ): array {
        $productIds = collect($items)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $products = Product::query()
            ->with('inventory')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $preparedItems = [];

        foreach ($items as $index => $item) {
            $productId = (int) $item['product_id'];

            $product = $products->get($productId);

            if (! $product) {
                throw ValidationException::withMessages([
                    "items.$index.product_id" =>
                        'The selected product was not found.',
                ]);
            }

            if (! $product->status) {
                throw ValidationException::withMessages([
                    "items.$index.product_id" =>
                        'The selected product is inactive.',
                ]);
            }

            $quantity = (float) $item['quantity'];

            $unitPrice = (float) $item['unit_price'];

            $discountAmount = (float) (
                $item['discount_amount'] ?? 0
            );

            $taxAmount = (float) (
                $item['tax_amount'] ?? 0
            );

            $lineSubtotal = $quantity * $unitPrice;

            if ($discountAmount > $lineSubtotal) {
                throw ValidationException::withMessages([
                    "items.$index.discount_amount" =>
                        'Discount cannot exceed the line subtotal.',
                ]);
            }

            if (
                $complete
                && $quantity > $product->availableStock()
            ) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" =>
                        'Insufficient stock for '
                        .$product->name
                        .'. Available stock: '
                        .number_format(
                            $product->availableStock(),
                            3
                        ),
                ]);
            }

            $preparedItems[] = [
                'product_id' => $product->id,

                'quantity' => $quantity,

                'returned_quantity' => 0,

                'unit_price' => $unitPrice,

                'discount_amount' => $discountAmount,

                'tax_amount' => $taxAmount,

                'line_total' => max(
                    0,
                    $lineSubtotal
                    - $discountAmount
                    + $taxAmount
                ),
            ];
        }

        return $preparedItems;
    }

    /**
     * Prepared items থেকে final totals হিসাব করবে।
     *
     * @param array<int, array<string, int|float>> $items
     * @return array<string, float>
     */
    private function calculatePreparedTotals(
        array $items
    ): array {
        $subtotal = 0;
        $discountAmount = 0;
        $taxAmount = 0;

        foreach ($items as $item) {
            $subtotal +=
                (float) $item['quantity']
                * (float) $item['unit_price'];

            $discountAmount +=
                (float) $item['discount_amount'];

            $taxAmount +=
                (float) $item['tax_amount'];
        }

        $shippingAmount = max(
            0,
            (float) $this->shippingAmount
        );

        return [
            'subtotal' => $subtotal,

            'discount_amount' => $discountAmount,

            'tax_amount' => $taxAmount,

            'shipping_amount' => $shippingAmount,

            'grand_total' => max(
                0,
                $subtotal
                - $discountAmount
                + $taxAmount
                + $shippingAmount
            ),
        ];
    }

    /**
     * Payment status নির্ধারণ করবে।
     */
    private function determinePaymentStatus(
        float $grandTotal,
        float $paidAmount
    ): string {
        if ($paidAmount <= 0) {
            return Sale::PAYMENT_UNPAID;
        }

        if (
            $grandTotal > 0
            && $paidAmount >= $grandTotal
        ) {
            return Sale::PAYMENT_PAID;
        }

        return Sale::PAYMENT_PARTIAL;
    }

    /**
     * Unique sale number তৈরি করবে।
     */
    private function generateSaleNumber(): string
    {
        do {
            $saleNumber = 'SAL-'
                .now()->format('Ymd')
                .'-'
                .Str::upper(Str::random(6));
        } while (
            Sale::withTrashed()
                ->where('sale_number', $saleNumber)
                ->exists()
        );

        return $saleNumber;
    }

    /**
     * নতুন empty product row.
     *
     * @return array<string, string>
     */
    private function newItem(): array
    {
        return [
            'row_key' => (string) Str::uuid(),
            'product_id' => '',
            'quantity' => '1',
            'unit_price' => '0',
            'discount_amount' => '0',
            'tax_amount' => '0',
        ];
    }

    /**
     * Successful save-এর পর form reset করবে।
     */
    private function resetForm(): void
    {
        $this->customerId = null;
        $this->saleDate = now()->format('Y-m-d');
        $this->shippingAmount = '0';
        $this->paidAmount = '0';
        $this->notes = '';

        $this->items = [
            $this->newItem(),
        ];

        $this->resetValidation();

        unset($this->totals);
    }
};

?>

<div class="space-y-6">
    {{-- Page heading --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Create Sale
        </h1>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Create a draft sale or complete it and reduce inventory stock.
        </p>
    </div>

    {{-- Success message --}}
    @if (session()->has('success'))
        <div
            class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
        >
            {{ session('success') }}
        </div>
    @endif

    {{-- General sale error --}}
    @error('sale')
        <div
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
        >
            {{ $message }}
        </div>
    @enderror

    @can('create sales')
        <form
            wire:submit="saveDraft"
            class="space-y-6"
        >
            {{-- Sale information --}}
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="mb-5">
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Sale Information
                    </h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Select the customer and sale date.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    {{-- Customer --}}
                    <div>
                        <label
                            for="customerId"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Customer
                        </label>

                        <select
                            id="customerId"
                            wire:model="customerId"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="">
                                Walk-in Customer
                            </option>

                            @foreach ($this->customers as $customer)
                                <option value="{{ $customer->id }}">
                                    {{ $customer->name }}

                                    @if ($customer->phone)
                                        — {{ $customer->phone }}
                                    @endif
                                </option>
                            @endforeach
                        </select>

                        @error('customerId')
                            <p class="mt-1 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Sale date --}}
                    <div>
                        <label
                            for="saleDate"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Sale Date
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="saleDate"
                            type="date"
                            wire:model="saleDate"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >

                        @error('saleDate')
                            <p class="mt-1 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Product items --}}
            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div
                    class="flex flex-col gap-4 border-b border-gray-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700"
                >
                    <div>
                        <h2
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Sale Products
                        </h2>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Select products and enter sale quantities.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="addItem"
                        class="inline-flex items-center justify-center rounded-lg bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 dark:bg-blue-950 dark:text-blue-300"
                    >
                        + Add Product
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table
                        class="min-w-[1250px] w-full text-left text-sm"
                    >
                        <thead
                            class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                        >
                            <tr>
                                <th class="px-4 py-3">
                                    Product
                                </th>

                                <th class="px-4 py-3 text-right">
                                    Available
                                </th>

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
                                    Line Total
                                </th>

                                <th class="px-4 py-3 text-right">
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            @foreach ($items as $index => $item)
                                @php
                                    $selectedProduct =
                                        $this->products->firstWhere(
                                            'id',
                                            (int) (
                                                $item['product_id']
                                                ?? 0
                                            )
                                        );

                                    $availableStock =
                                        $selectedProduct
                                            ?->availableStock() ?? 0;

                                    $lineTotal =
                                        $this->totals[
                                            'line_totals'
                                        ][$index] ?? 0;
                                @endphp

                                <tr
                                    wire:key="sale-item-{{ $item['row_key'] }}"
                                    class="align-top"
                                >
                                    {{-- Product --}}
                                    <td class="min-w-72 px-4 py-4">
                                        <select
                                            wire:model.live="items.{{ $index }}.product_id"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >
                                            <option value="">
                                                Select product
                                            </option>

                                            @foreach (
                                                $this->products
                                                as $product
                                            )
                                                <option
                                                    value="{{ $product->id }}"
                                                >
                                                    {{ $product->name }}
                                                    — {{ $product->sku }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @if ($selectedProduct)
                                            <p
                                                class="mt-1 text-xs text-gray-500"
                                            >
                                                Selling price:
                                                ৳{{ number_format(
                                                    (float) $selectedProduct
                                                        ->selling_price,
                                                    2
                                                ) }}
                                            </p>
                                        @endif

                                        @error(
                                            "items.$index.product_id"
                                        )
                                            <p
                                                class="mt-1 text-xs text-red-600"
                                            >
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>

                                    {{-- Available --}}
                                    <td
                                        class="whitespace-nowrap px-4 py-4 text-right"
                                    >
                                        <span
                                            class="{{ $availableStock > 0
                                                ? 'font-semibold text-green-700 dark:text-green-400'
                                                : 'font-semibold text-red-600' }}"
                                        >
                                            {{ number_format(
                                                (float) $availableStock,
                                                3
                                            ) }}
                                        </span>

                                        @if ($selectedProduct?->unit)
                                            <span
                                                class="ml-1 text-xs text-gray-500"
                                            >
                                                {{ $selectedProduct
                                                    ->unit
                                                    ->short_name }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Quantity --}}
                                    <td class="min-w-40 px-4 py-4">
                                        <input
                                            type="number"
                                            min="0.001"
                                            step="0.001"
                                            wire:model.live.debounce.300ms="items.{{ $index }}.quantity"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-right text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >

                                        @error(
                                            "items.$index.quantity"
                                        )
                                            <p
                                                class="mt-1 text-xs text-red-600"
                                            >
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>

                                    {{-- Unit price --}}
                                    <td class="min-w-44 px-4 py-4">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            wire:model.live.debounce.300ms="items.{{ $index }}.unit_price"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-right text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >

                                        @error(
                                            "items.$index.unit_price"
                                        )
                                            <p
                                                class="mt-1 text-xs text-red-600"
                                            >
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>

                                    {{-- Discount --}}
                                    <td class="min-w-40 px-4 py-4">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            wire:model.live.debounce.300ms="items.{{ $index }}.discount_amount"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-right text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >

                                        @error(
                                            "items.$index.discount_amount"
                                        )
                                            <p
                                                class="mt-1 text-xs text-red-600"
                                            >
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>

                                    {{-- Tax --}}
                                    <td class="min-w-40 px-4 py-4">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            wire:model.live.debounce.300ms="items.{{ $index }}.tax_amount"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-right text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >

                                        @error(
                                            "items.$index.tax_amount"
                                        )
                                            <p
                                                class="mt-1 text-xs text-red-600"
                                            >
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </td>

                                    {{-- Line total --}}
                                    <td
                                        class="whitespace-nowrap px-4 py-4 text-right font-bold text-gray-900 dark:text-white"
                                    >
                                        ৳{{ number_format(
                                            (float) $lineTotal,
                                            2
                                        ) }}
                                    </td>

                                    {{-- Remove --}}
                                    <td class="px-4 py-4 text-right">
                                        <button
                                            type="button"
                                            wire:click="removeItem({{ $index }})"
                                            class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-100 dark:bg-red-950 dark:text-red-300"
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

            <div class="grid gap-6 xl:grid-cols-2">
                {{-- Additional information --}}
                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Payment & Notes
                    </h2>

                    <div class="mt-5 space-y-5">
                        {{-- Shipping --}}
                        <div>
                            <label
                                for="shippingAmount"
                                class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Shipping Amount
                            </label>

                            <input
                                id="shippingAmount"
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live.debounce.300ms="shippingAmount"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-right text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >

                            @error('shippingAmount')
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Paid amount --}}
                        <div>
                            <label
                                for="paidAmount"
                                class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Paid Amount
                            </label>

                            <input
                                id="paidAmount"
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live.debounce.300ms="paidAmount"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-right text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >

                            @error('paidAmount')
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label
                                for="notes"
                                class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Notes
                            </label>

                            <textarea
                                id="notes"
                                wire:model="notes"
                                rows="4"
                                placeholder="Optional sale notes..."
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            ></textarea>

                            @error('notes')
                                <p class="mt-1 text-xs text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Sale summary --}}
                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Sale Summary
                    </h2>

                    <div class="mt-5 space-y-4">
                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="text-gray-600 dark:text-gray-400"
                            >
                                Subtotal
                            </span>

                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                ৳{{ number_format(
                                    $this->totals['subtotal'],
                                    2
                                ) }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="text-gray-600 dark:text-gray-400"
                            >
                                Discount
                            </span>

                            <span
                                class="font-semibold text-red-600"
                            >
                                - ৳{{ number_format(
                                    $this->totals[
                                        'discount_amount'
                                    ],
                                    2
                                ) }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="text-gray-600 dark:text-gray-400"
                            >
                                Tax
                            </span>

                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                ৳{{ number_format(
                                    $this->totals['tax_amount'],
                                    2
                                ) }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="text-gray-600 dark:text-gray-400"
                            >
                                Shipping
                            </span>

                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                ৳{{ number_format(
                                    $this->totals[
                                        'shipping_amount'
                                    ],
                                    2
                                ) }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-gray-200 pt-4 text-lg dark:border-gray-700"
                        >
                            <span
                                class="font-bold text-gray-900 dark:text-white"
                            >
                                Grand Total
                            </span>

                            <span class="font-bold text-blue-600">
                                ৳{{ number_format(
                                    $this->totals[
                                        'grand_total'
                                    ],
                                    2
                                ) }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="text-gray-600 dark:text-gray-400"
                            >
                                Paid Amount
                            </span>

                            <span
                                class="font-semibold text-green-600"
                            >
                                ৳{{ number_format(
                                    $this->totals[
                                        'paid_amount'
                                    ],
                                    2
                                ) }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="font-medium text-gray-700 dark:text-gray-300"
                            >
                                Due Amount
                            </span>

                            <span
                                class="font-bold text-red-600"
                            >
                                ৳{{ number_format(
                                    $this->totals[
                                        'due_amount'
                                    ],
                                    2
                                ) }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700"
                        >
                            <span
                                class="text-gray-600 dark:text-gray-400"
                            >
                                Payment Status
                            </span>

                            @if (
                                $this->totals['payment_status']
                                === Sale::PAYMENT_PAID
                            )
                                <span
                                    class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                >
                                    Paid
                                </span>
                            @elseif (
                                $this->totals['payment_status']
                                === Sale::PAYMENT_PARTIAL
                            )
                                <span
                                    class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                                >
                                    Partial
                                </span>
                            @else
                                <span
                                    class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                >
                                    Unpaid
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div
                class="flex flex-col-reverse gap-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:justify-end dark:border-gray-700 dark:bg-gray-900"
            >
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="saveDraft,completeSale"
                    class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Save as Draft
                </button>

                <button
                    type="button"
                    wire:click="completeSale"
                    wire:confirm="Complete this sale and reduce product stock?"
                    wire:loading.attr="disabled"
                    wire:target="saveDraft,completeSale"
                    class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Complete Sale
                </button>
            </div>
        </form>
    @else
        <div
            class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
        >
            You do not have permission to create sales.
        </div>
    @endcan
</div>