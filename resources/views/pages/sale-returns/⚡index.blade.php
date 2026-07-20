<?php

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\SaleReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $saleId = '';
    public string $returnDate = '';
    public string $reason = '';
    public string $notes = '';
    public array $items = [];

    public function mount(): void
    {
        $this->returnDate = now()->format('Y-m-d');
    }

    public function updatedSaleId(): void
    {
        $this->items = [];
        unset($this->selectedSale, $this->totals);

        if ($this->saleId === '') {
            return;
        }

        $sale = $this->selectedSale;

        foreach ($sale->items as $item) {
            $remaining = max(0, (float) $item->quantity - (float) $item->returned_quantity);

            if ($remaining <= 0) {
                continue;
            }

            $ratio = (float) $item->quantity > 0 ? 1 / (float) $item->quantity : 0;

            $this->items[] = [
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'sku' => $item->product->sku,
                'unit' => $item->product->unit?->short_name,
                'max_quantity' => $remaining,
                'quantity' => '0',
                'unit_price' => (string) $item->unit_price,
                'discount_per_unit' => (float) $item->discount_amount * $ratio,
                'tax_per_unit' => (float) $item->tax_amount * $ratio,
            ];
        }
    }

    public function updatedItems(): void
    {
        unset($this->totals);
    }

    #[Computed]
    public function completedSales()
    {
        return Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->with('customer:id,name')
            ->latest('sale_date')
            ->limit(100)
            ->get(['id', 'sale_number', 'customer_id', 'sale_date', 'grand_total']);
    }

    #[Computed]
    public function selectedSale(): ?Sale
    {
        if ($this->saleId === '') {
            return null;
        }

        return Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->with(['customer', 'items.product.unit'])
            ->findOrFail((int) $this->saleId);
    }

    #[Computed]
    public function totals(): array
    {
        $subtotal = 0;
        $discount = 0;
        $tax = 0;

        foreach ($this->items as $item) {
            $quantity = max(0, (float) ($item['quantity'] ?? 0));
            $subtotal += $quantity * (float) $item['unit_price'];
            $discount += $quantity * (float) $item['discount_per_unit'];
            $tax += $quantity * (float) $item['tax_per_unit'];
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'refund' => max(0, $subtotal - $discount + $tax),
        ];
    }

    public function saveDraft(): void
    {
        $this->store(false);
    }

    public function saveAndComplete(): void
    {
        $this->store(true);
    }

    private function store(bool $complete): void
    {
        abort_unless(auth()->user()->can('create sale returns'), 403);

        $validated = $this->validate([
            'saleId' => ['required', 'integer', 'exists:sales,id'],
            'returnDate' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $selectedItems = collect($this->items)
            ->filter(fn ($item) => (float) ($item['quantity'] ?? 0) > 0)
            ->values();

        if ($selectedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'sale_return' => 'Enter a return quantity for at least one product.',
            ]);
        }

        foreach ($selectedItems as $index => $item) {
            if ((float) $item['quantity'] > (float) $item['max_quantity']) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'Return quantity exceeds the remaining returnable quantity.',
                ]);
            }
        }

        $saleReturn = DB::transaction(function () use ($validated, $selectedItems, $complete): SaleReturn {
            $totals = $this->totals;

            $saleReturn = SaleReturn::query()->create([
                'return_number' => $this->generateNumber(),
                'sale_id' => (int) $validated['saleId'],
                'return_date' => $validated['returnDate'],
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'refund_amount' => $totals['refund'],
                'status' => SaleReturn::STATUS_DRAFT,
                'reason' => filled($validated['reason']) ? trim($validated['reason']) : null,
                'notes' => filled($validated['notes']) ? trim($validated['notes']) : null,
                'created_by' => auth()->id(),
            ]);

            foreach ($selectedItems as $item) {
                $quantity = (float) $item['quantity'];
                $discount = $quantity * (float) $item['discount_per_unit'];
                $tax = $quantity * (float) $item['tax_per_unit'];

                $saleReturn->items()->create([
                    'sale_item_id' => $item['sale_item_id'],
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'refund_amount' => max(0, $quantity * (float) $item['unit_price'] - $discount + $tax),
                ]);
            }

            return $complete
                ? app(SaleReturnService::class)->complete($saleReturn, auth()->user())
                : $saleReturn;
        }, attempts: 3);

        session()->flash('success', ($complete ? 'Sale return completed: ' : 'Sale return draft saved: ').$saleReturn->return_number);

        $this->reset(['saleId', 'reason', 'notes', 'items']);
        $this->returnDate = now()->format('Y-m-d');
        unset($this->selectedSale, $this->totals);
    }

    private function generateNumber(): string
    {
        do {
            $number = 'SRT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (SaleReturn::withTrashed()->where('return_number', $number)->exists());

        return $number;
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Create Sale Return</h1>
            <p class="text-sm text-gray-500">Return products from a completed sale and restore stock.</p>
        </div>
        <a href="{{ route('sale-returns.manage') }}" wire:navigate class="rounded-lg border px-4 py-2 text-sm font-semibold">Manage Returns</a>
    </div>

    @if (session()->has('success'))
        <div class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
    @endif
    @error('sale_return')
        <div class="rounded-lg bg-red-50 p-4 text-red-700">{{ $message }}</div>
    @enderror

    @can('create sale returns')
    <form wire:submit="saveDraft" class="space-y-6">
        <div class="grid gap-4 rounded-xl border bg-white p-5 md:grid-cols-2 dark:bg-gray-900">
            <div>
                <label class="mb-2 block text-sm font-medium">Completed Sale</label>
                <select wire:model.live="saleId" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800">
                    <option value="">Select sale</option>
                    @foreach ($this->completedSales as $sale)
                        <option value="{{ $sale->id }}">{{ $sale->sale_number }} — {{ $sale->customer?->name ?? 'Walk-in Customer' }}</option>
                    @endforeach
                </select>
                @error('saleId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">Return Date</label>
                <input type="date" wire:model="returnDate" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800">
                @error('returnDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">Reason</label>
                <input wire:model="reason" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800" placeholder="Damaged, wrong item, customer request...">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">Notes</label>
                <input wire:model="notes" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800" placeholder="Optional notes">
            </div>
        </div>

        @if ($this->selectedSale)
            <div class="overflow-hidden rounded-xl border bg-white dark:bg-gray-900">
                <div class="border-b p-4">
                    <h2 class="font-semibold">Returnable Products</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[900px] w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr><th class="px-4 py-3">Product</th><th class="px-4 py-3 text-right">Available to Return</th><th class="px-4 py-3 text-right">Unit Price</th><th class="px-4 py-3 text-right">Return Quantity</th><th class="px-4 py-3 text-right">Refund</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($items as $index => $item)
                                @php
                                    $quantity = (float) ($item['quantity'] ?? 0);
                                    $refund = max(0, $quantity * (float) $item['unit_price'] - $quantity * (float) $item['discount_per_unit'] + $quantity * (float) $item['tax_per_unit']);
                                @endphp
                                <tr wire:key="sale-return-item-{{ $item['sale_item_id'] }}">
                                    <td class="px-4 py-4"><p class="font-semibold">{{ $item['product_name'] }}</p><p class="text-xs text-gray-500">{{ $item['sku'] }}</p></td>
                                    <td class="px-4 py-4 text-right">{{ number_format($item['max_quantity'], 3) }} {{ $item['unit'] }}</td>
                                    <td class="px-4 py-4 text-right">৳{{ number_format((float) $item['unit_price'], 2) }}</td>
                                    <td class="px-4 py-4"><input type="number" min="0" max="{{ $item['max_quantity'] }}" step="0.001" wire:model.live.debounce.300ms="items.{{ $index }}.quantity" class="w-full rounded-lg border px-3 py-2 text-right dark:bg-gray-800"></td>
                                    <td class="px-4 py-4 text-right font-semibold">৳{{ number_format($refund, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">This sale has no returnable products.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ml-auto max-w-md rounded-xl border bg-white p-5 dark:bg-gray-900">
                <div class="space-y-3">
                    <div class="flex justify-between"><span>Subtotal</span><span>৳{{ number_format($this->totals['subtotal'], 2) }}</span></div>
                    <div class="flex justify-between"><span>Discount</span><span>- ৳{{ number_format($this->totals['discount'], 2) }}</span></div>
                    <div class="flex justify-between"><span>Tax</span><span>৳{{ number_format($this->totals['tax'], 2) }}</span></div>
                    <div class="flex justify-between border-t pt-3 text-lg font-bold"><span>Refund</span><span>৳{{ number_format($this->totals['refund'], 2) }}</span></div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="submit" class="rounded-lg border px-5 py-2.5 font-semibold">Save Draft</button>
                @can('complete sale returns')
                    <button type="button" wire:click="saveAndComplete" wire:confirm="Complete this return and restore stock?" class="rounded-lg bg-green-600 px-5 py-2.5 font-semibold text-white">Complete Return</button>
                @endcan
            </div>
        @endif
    </form>
    @else
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-800">You do not have permission to create sale returns.</div>
    @endcan
</div>
