<?php

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\PurchaseReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $purchaseId = '';
    public string $returnDate = '';
    public string $reason = '';
    public string $notes = '';
    public array $items = [];

    public function mount(): void
    {
        $this->returnDate = now()->format('Y-m-d');
    }

    public function updatedPurchaseId(): void
    {
        $this->items = [];
        unset($this->selectedPurchase, $this->totals);

        if ($this->purchaseId === '') {
            return;
        }

        foreach ($this->selectedPurchase->items as $item) {
            $alreadyReturned = (float) PurchaseReturnItem::query()
                ->where('purchase_item_id', $item->id)
                ->whereHas('purchaseReturn', fn ($query) => $query->where('status', PurchaseReturn::STATUS_COMPLETED))
                ->sum('quantity');

            $remaining = max(0, (float) $item->received_quantity - $alreadyReturned);

            if ($remaining <= 0) {
                continue;
            }

            $ratio = (float) $item->quantity > 0 ? 1 / (float) $item->quantity : 0;

            $this->items[] = [
                'purchase_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'sku' => $item->product->sku,
                'unit' => $item->product->unit?->short_name,
                'max_quantity' => $remaining,
                'quantity' => '0',
                'unit_cost' => (string) $item->unit_cost,
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
    public function receivedPurchases()
    {
        return Purchase::query()
            ->where('status', Purchase::STATUS_RECEIVED)
            ->with('supplier:id,name,company_name')
            ->latest('purchase_date')
            ->limit(100)
            ->get(['id', 'purchase_number', 'supplier_id', 'purchase_date', 'grand_total']);
    }

    #[Computed]
    public function selectedPurchase(): ?Purchase
    {
        if ($this->purchaseId === '') {
            return null;
        }

        return Purchase::query()
            ->where('status', Purchase::STATUS_RECEIVED)
            ->with(['supplier', 'items.product.unit'])
            ->findOrFail((int) $this->purchaseId);
    }

    #[Computed]
    public function totals(): array
    {
        $subtotal = 0;
        $discount = 0;
        $tax = 0;

        foreach ($this->items as $item) {
            $quantity = max(0, (float) ($item['quantity'] ?? 0));
            $subtotal += $quantity * (float) $item['unit_cost'];
            $discount += $quantity * (float) $item['discount_per_unit'];
            $tax += $quantity * (float) $item['tax_per_unit'];
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => max(0, $subtotal - $discount + $tax),
        ];
    }

    public function saveDraft(): void { $this->store(false); }
    public function saveAndComplete(): void { $this->store(true); }

    private function store(bool $complete): void
    {
        abort_unless(auth()->user()->can('create purchase returns'), 403);

        $validated = $this->validate([
            'purchaseId' => ['required', 'integer', 'exists:purchases,id'],
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
                'purchase_return' => 'Enter a return quantity for at least one product.',
            ]);
        }

        foreach ($selectedItems as $index => $item) {
            if ((float) $item['quantity'] > (float) $item['max_quantity']) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'Return quantity exceeds the remaining returnable quantity.',
                ]);
            }
        }

        $purchaseReturn = DB::transaction(function () use ($validated, $selectedItems, $complete): PurchaseReturn {
            $totals = $this->totals;

            $purchaseReturn = PurchaseReturn::query()->create([
                'return_number' => $this->generateNumber(),
                'purchase_id' => (int) $validated['purchaseId'],
                'return_date' => $validated['returnDate'],
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'total_amount' => $totals['total'],
                'status' => PurchaseReturn::STATUS_DRAFT,
                'reason' => filled($validated['reason']) ? trim($validated['reason']) : null,
                'notes' => filled($validated['notes']) ? trim($validated['notes']) : null,
                'created_by' => auth()->id(),
            ]);

            foreach ($selectedItems as $item) {
                $quantity = (float) $item['quantity'];
                $discount = $quantity * (float) $item['discount_per_unit'];
                $tax = $quantity * (float) $item['tax_per_unit'];

                $purchaseReturn->items()->create([
                    'purchase_item_id' => $item['purchase_item_id'],
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_cost' => $item['unit_cost'],
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'line_total' => max(0, $quantity * (float) $item['unit_cost'] - $discount + $tax),
                ]);
            }

            return $complete
                ? app(PurchaseReturnService::class)->complete($purchaseReturn, auth()->user())
                : $purchaseReturn;
        }, attempts: 3);

        session()->flash('success', ($complete ? 'Purchase return completed: ' : 'Purchase return draft saved: ').$purchaseReturn->return_number);

        $this->reset(['purchaseId', 'reason', 'notes', 'items']);
        $this->returnDate = now()->format('Y-m-d');
        unset($this->selectedPurchase, $this->totals);
    }

    private function generateNumber(): string
    {
        do {
            $number = 'PRT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (PurchaseReturn::withTrashed()->where('return_number', $number)->exists());

        return $number;
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div><h1 class="text-2xl font-bold">Create Purchase Return</h1><p class="text-sm text-gray-500">Return received products to a supplier and reduce stock.</p></div>
        <a href="{{ route('purchase-returns.manage') }}" wire:navigate class="rounded-lg border px-4 py-2 text-sm font-semibold">Manage Returns</a>
    </div>

    @if (session()->has('success')) <div class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @error('purchase_return') <div class="rounded-lg bg-red-50 p-4 text-red-700">{{ $message }}</div> @enderror

    @can('create purchase returns')
    <form wire:submit="saveDraft" class="space-y-6">
        <div class="grid gap-4 rounded-xl border bg-white p-5 md:grid-cols-2 dark:bg-gray-900">
            <div>
                <label class="mb-2 block text-sm font-medium">Received Purchase</label>
                <select wire:model.live="purchaseId" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"><option value="">Select purchase</option>@foreach ($this->receivedPurchases as $purchase)<option value="{{ $purchase->id }}">{{ $purchase->purchase_number }} — {{ $purchase->supplier->company_name ?? $purchase->supplier->name }}</option>@endforeach</select>
                @error('purchaseId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div><label class="mb-2 block text-sm font-medium">Return Date</label><input type="date" wire:model="returnDate" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"></div>
            <div><label class="mb-2 block text-sm font-medium">Reason</label><input wire:model="reason" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800" placeholder="Damaged, wrong delivery..."></div>
            <div><label class="mb-2 block text-sm font-medium">Notes</label><input wire:model="notes" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800" placeholder="Optional notes"></div>
        </div>

        @if ($this->selectedPurchase)
            <div class="overflow-hidden rounded-xl border bg-white dark:bg-gray-900">
                <div class="border-b p-4"><h2 class="font-semibold">Returnable Products</h2></div>
                <div class="overflow-x-auto"><table class="min-w-[900px] w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-4 py-3">Product</th><th class="px-4 py-3 text-right">Available to Return</th><th class="px-4 py-3 text-right">Unit Cost</th><th class="px-4 py-3 text-right">Return Quantity</th><th class="px-4 py-3 text-right">Total</th></tr></thead><tbody class="divide-y">@forelse ($items as $index => $item)@php $quantity=(float)($item['quantity']??0); $total=max(0,$quantity*(float)$item['unit_cost']-$quantity*(float)$item['discount_per_unit']+$quantity*(float)$item['tax_per_unit']); @endphp<tr wire:key="purchase-return-item-{{ $item['purchase_item_id'] }}"><td class="px-4 py-4"><p class="font-semibold">{{ $item['product_name'] }}</p><p class="text-xs text-gray-500">{{ $item['sku'] }}</p></td><td class="px-4 py-4 text-right">{{ number_format($item['max_quantity'],3) }} {{ $item['unit'] }}</td><td class="px-4 py-4 text-right">৳{{ number_format((float)$item['unit_cost'],2) }}</td><td class="px-4 py-4"><input type="number" min="0" max="{{ $item['max_quantity'] }}" step="0.001" wire:model.live.debounce.300ms="items.{{ $index }}.quantity" class="w-full rounded-lg border px-3 py-2 text-right dark:bg-gray-800"></td><td class="px-4 py-4 text-right font-semibold">৳{{ number_format($total,2) }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No returnable products.</td></tr>@endforelse</tbody></table></div>
            </div>

            <div class="ml-auto max-w-md rounded-xl border bg-white p-5 dark:bg-gray-900"><div class="space-y-3"><div class="flex justify-between"><span>Subtotal</span><span>৳{{ number_format($this->totals['subtotal'],2) }}</span></div><div class="flex justify-between"><span>Discount</span><span>- ৳{{ number_format($this->totals['discount'],2) }}</span></div><div class="flex justify-between"><span>Tax</span><span>৳{{ number_format($this->totals['tax'],2) }}</span></div><div class="flex justify-between border-t pt-3 text-lg font-bold"><span>Total Return</span><span>৳{{ number_format($this->totals['total'],2) }}</span></div></div></div>
            <div class="flex justify-end gap-3"><button type="submit" class="rounded-lg border px-5 py-2.5 font-semibold">Save Draft</button>@can('complete purchase returns')<button type="button" wire:click="saveAndComplete" wire:confirm="Complete this return and reduce stock?" class="rounded-lg bg-red-600 px-5 py-2.5 font-semibold text-white">Complete Return</button>@endcan</div>
        @endif
    </form>
    @else
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-800">You do not have permission to create purchase returns.</div>
    @endcan
</div>
