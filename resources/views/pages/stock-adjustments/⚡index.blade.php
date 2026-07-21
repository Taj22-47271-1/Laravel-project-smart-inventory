<?php

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\StockAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $adjustmentDate = '';
    public string $reason = '';
    public string $notes = '';
    public array $items = [];

    public function mount(): void
    {
        $this->adjustmentDate = now()->format('Y-m-d');
        $this->items = [$this->newItem()];
    }

    public function addItem(): void
    {
        $this->items[] = $this->newItem();
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) === 1) {
            $this->items = [$this->newItem()];
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems(mixed $value, string $key): void
    {
        $segments = explode('.', $key);

        if (count($segments) === 2 && $segments[1] === 'product_id') {
            $index = (int) $segments[0];
            $product = $this->products->firstWhere('id', (int) $value);

            if ($product) {
                $this->items[$index]['unit_cost'] = (string) (
                    $product->inventory?->average_cost
                    ?? $product->purchase_price
                    ?? 0
                );
            }
        }
    }

    #[Computed]
    public function products()
    {
        return Product::query()
            ->where('status', true)
            ->with(['unit:id,short_name', 'inventory:id,product_id,quantity,reserved_quantity,average_cost'])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit_id', 'purchase_price']);
    }

    public function saveDraft(): void { $this->store(false); }
    public function saveAndComplete(): void { $this->store(true); }

    private function store(bool $complete): void
    {
        abort_unless(auth()->user()->can('create stock adjustments'), 403);

        $validated = $this->validate([
            'adjustmentDate' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.direction' => ['required', 'in:in,out'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $duplicates = collect($validated['items'])
            ->map(fn ($item) => $item['product_id'].'-'.$item['direction'])
            ->duplicates();

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'adjustment' => 'The same product and direction cannot be added twice.',
            ]);
        }

        $adjustment = DB::transaction(function () use ($validated, $complete): StockAdjustment {
            $adjustment = StockAdjustment::query()->create([
                'adjustment_number' => $this->generateNumber(),
                'adjustment_date' => $validated['adjustmentDate'],
                'status' => StockAdjustment::STATUS_DRAFT,
                'reason' => trim($validated['reason']),
                'notes' => filled($validated['notes']) ? trim($validated['notes']) : null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $adjustment->items()->create([
                    'product_id' => (int) $item['product_id'],
                    'direction' => $item['direction'],
                    'quantity' => (float) $item['quantity'],
                    'unit_cost' => filled($item['unit_cost'] ?? null) ? (float) $item['unit_cost'] : null,
                    'notes' => filled($item['notes'] ?? null) ? trim($item['notes']) : null,
                ]);
            }

            return $complete
                ? app(StockAdjustmentService::class)->complete($adjustment, auth()->user())
                : $adjustment;
        }, attempts: 3);

        session()->flash('success', ($complete ? 'Adjustment completed: ' : 'Adjustment draft saved: ').$adjustment->adjustment_number);

        $this->reset(['reason', 'notes']);
        $this->adjustmentDate = now()->format('Y-m-d');
        $this->items = [$this->newItem()];
    }

    private function newItem(): array
    {
        return [
            'row_key' => (string) Str::uuid(),
            'product_id' => '',
            'direction' => StockAdjustmentItem::DIRECTION_IN,
            'quantity' => '1',
            'unit_cost' => '0',
            'notes' => '',
        ];
    }

    private function generateNumber(): string
    {
        do {
            $number = 'ADJ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (StockAdjustment::withTrashed()->where('adjustment_number', $number)->exists());

        return $number;
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h1 class="text-2xl font-bold">Stock Adjustment</h1><p class="text-sm text-gray-500">Correct stock with a traceable adjustment record.</p></div><a href="{{ route('stock-adjustments.manage') }}" wire:navigate class="rounded-lg border px-4 py-2 font-semibold">Manage Adjustments</a></div>
    @if (session()->has('success')) <div class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @error('adjustment') <div class="rounded-lg bg-red-50 p-4 text-red-700">{{ $message }}</div> @enderror

    @can('create stock adjustments')
    <form wire:submit="saveDraft" class="space-y-6">
        <div class="grid gap-4 rounded-xl border bg-white p-5 md:grid-cols-3 dark:bg-gray-900">
            <div><label class="mb-2 block text-sm font-medium">Date</label><input type="date" wire:model="adjustmentDate" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"></div>
            <div><label class="mb-2 block text-sm font-medium">Reason</label><input wire:model="reason" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800" placeholder="Physical count correction..."></div>
            <div><label class="mb-2 block text-sm font-medium">Notes</label><input wire:model="notes" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800" placeholder="Optional notes"></div>
        </div>

        <div class="overflow-hidden rounded-xl border bg-white dark:bg-gray-900">
            <div class="flex items-center justify-between border-b p-4"><h2 class="font-semibold">Adjustment Items</h2><button type="button" wire:click="addItem" class="rounded-lg bg-blue-50 px-4 py-2 font-semibold text-blue-700">+ Add Product</button></div>
            <div class="overflow-x-auto"><table class="min-w-[1100px] w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-4 py-3">Product</th><th class="px-4 py-3">Current Stock</th><th class="px-4 py-3">Direction</th><th class="px-4 py-3 text-right">Quantity</th><th class="px-4 py-3 text-right">Unit Cost</th><th class="px-4 py-3">Item Note</th><th class="px-4 py-3 text-right">Action</th></tr></thead><tbody class="divide-y">@foreach ($items as $index => $item)@php $product=$this->products->firstWhere('id',(int)($item['product_id']??0)); @endphp<tr wire:key="adjustment-item-{{ $item['row_key'] }}"><td class="px-4 py-4"><select wire:model.live="items.{{ $index }}.product_id" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"><option value="">Select product</option>@foreach ($this->products as $option)<option value="{{ $option->id }}">{{ $option->name }} — {{ $option->sku }}</option>@endforeach</select></td><td class="px-4 py-4">{{ number_format((float)($product?->inventory?->quantity??0),3) }} {{ $product?->unit?->short_name }}</td><td class="px-4 py-4"><select wire:model="items.{{ $index }}.direction" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"><option value="in">Stock In</option><option value="out">Stock Out</option></select></td><td class="px-4 py-4"><input type="number" min="0.001" step="0.001" wire:model="items.{{ $index }}.quantity" class="w-full rounded-lg border px-3 py-2.5 text-right dark:bg-gray-800"></td><td class="px-4 py-4"><input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_cost" class="w-full rounded-lg border px-3 py-2.5 text-right dark:bg-gray-800"></td><td class="px-4 py-4"><input wire:model="items.{{ $index }}.notes" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"></td><td class="px-4 py-4 text-right"><button type="button" wire:click="removeItem({{ $index }})" class="rounded bg-red-50 px-3 py-2 text-red-700">Remove</button></td></tr>@endforeach</tbody></table></div>
        </div>

        <div class="flex justify-end gap-3"><button type="submit" class="rounded-lg border px-5 py-2.5 font-semibold">Save Draft</button>@can('complete stock adjustments')<button type="button" wire:click="saveAndComplete" wire:confirm="Complete this stock adjustment?" class="rounded-lg bg-green-600 px-5 py-2.5 font-semibold text-white">Complete Adjustment</button>@endcan</div>
    </form>
    @else
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-800">You do not have permission to create stock adjustments.</div>
    @endcan
</div>
