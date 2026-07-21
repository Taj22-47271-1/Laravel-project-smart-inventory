<?php

use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
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

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }
    public function open(int $id): void { $this->selectedId = $id; unset($this->selectedAdjustment); }
    public function close(): void { $this->selectedId = null; unset($this->selectedAdjustment); }

    public function complete(int $id): void
    {
        try {
            app(StockAdjustmentService::class)->complete(StockAdjustment::findOrFail($id), auth()->user());
            session()->flash('success', 'Stock adjustment completed successfully.');
            unset($this->adjustments, $this->selectedAdjustment);
        } catch (ValidationException $exception) {
            $this->addError('workflow', collect($exception->errors())->flatten()->first());
        }
    }

    public function cancel(int $id): void
    {
        try {
            app(StockAdjustmentService::class)->cancel(StockAdjustment::findOrFail($id), auth()->user());
            session()->flash('success', 'Stock adjustment cancelled successfully.');
            unset($this->adjustments, $this->selectedAdjustment);
        } catch (ValidationException $exception) {
            $this->addError('workflow', collect($exception->errors())->flatten()->first());
        }
    }

    #[Computed]
    public function adjustments()
    {
        $search = trim($this->search);

        return StockAdjustment::query()
            ->with(['createdBy', 'completedBy'])
            ->withCount('items')
            ->when($search !== '', fn ($query) => $query->where('adjustment_number', 'like', "%{$search}%")->orWhere('reason', 'like', "%{$search}%"))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->latest('adjustment_date')
            ->paginate(10);
    }

    #[Computed]
    public function selectedAdjustment(): ?StockAdjustment
    {
        return $this->selectedId
            ? StockAdjustment::with(['items.product.unit', 'createdBy', 'completedBy'])->findOrFail($this->selectedId)
            : null;
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h1 class="text-2xl font-bold">Stock Adjustments</h1><p class="text-sm text-gray-500">Review and process stock corrections.</p></div><a href="{{ route('stock-adjustments.index') }}" wire:navigate class="rounded-lg bg-blue-600 px-4 py-2.5 font-semibold text-white">+ New Adjustment</a></div>
    @if (session()->has('success')) <div class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @error('workflow') <div class="rounded-lg bg-red-50 p-4 text-red-700">{{ $message }}</div> @enderror
    <div class="grid gap-4 rounded-xl border bg-white p-5 md:grid-cols-2 dark:bg-gray-900"><input type="search" wire:model.live.debounce.300ms="search" placeholder="Number or reason..." class="rounded-lg border px-3 py-2.5 dark:bg-gray-800"><select wire:model.live="status" class="rounded-lg border px-3 py-2.5 dark:bg-gray-800"><option value="">All statuses</option><option value="draft">Draft</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
    <div class="overflow-hidden rounded-xl border bg-white dark:bg-gray-900"><div class="overflow-x-auto"><table class="min-w-[950px] w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-4 py-3">Adjustment</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3 text-center">Items</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Created By</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y">@forelse ($this->adjustments as $adjustment)<tr wire:key="adjustment-{{ $adjustment->id }}"><td class="px-4 py-4 font-semibold">{{ $adjustment->adjustment_number }}</td><td class="px-4 py-4">{{ $adjustment->adjustment_date->format('d M Y') }}</td><td class="px-4 py-4">{{ $adjustment->reason }}</td><td class="px-4 py-4 text-center">{{ $adjustment->items_count }}</td><td class="px-4 py-4 capitalize">{{ $adjustment->status }}</td><td class="px-4 py-4">{{ $adjustment->createdBy->name }}</td><td class="px-4 py-4"><div class="flex justify-end gap-2"><button wire:click="open({{ $adjustment->id }})" class="rounded bg-gray-100 px-3 py-2">View</button>@if ($adjustment->isDraft()) @can('complete stock adjustments')<button wire:click="complete({{ $adjustment->id }})" wire:confirm="Complete this adjustment?" class="rounded bg-green-100 px-3 py-2 text-green-700">Complete</button>@endcan @can('cancel stock adjustments')<button wire:click="cancel({{ $adjustment->id }})" wire:confirm="Cancel this adjustment?" class="rounded bg-red-100 px-3 py-2 text-red-700">Cancel</button>@endcan @endif</div></td></tr>@empty<tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No stock adjustments found.</td></tr>@endforelse</tbody></table></div><div class="border-t p-4">{{ $this->adjustments->links() }}</div></div>
    @if ($this->selectedAdjustment)<div wire:click.self="close" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"><div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-6 dark:bg-gray-900"><div class="flex justify-between"><div><h2 class="text-xl font-bold">{{ $this->selectedAdjustment->adjustment_number }}</h2><p class="text-sm text-gray-500">{{ $this->selectedAdjustment->reason }}</p></div><button wire:click="close" class="rounded border px-3 py-2">Close</button></div><div class="mt-5 overflow-x-auto"><table class="w-full min-w-[700px] text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-3 py-2 text-left">Product</th><th class="px-3 py-2">Direction</th><th class="px-3 py-2 text-right">Quantity</th><th class="px-3 py-2 text-right">Unit Cost</th></tr></thead><tbody class="divide-y">@foreach ($this->selectedAdjustment->items as $item)<tr><td class="px-3 py-3">{{ $item->product->name }}</td><td class="px-3 py-3 uppercase">{{ $item->direction }}</td><td class="px-3 py-3 text-right">{{ number_format((float)$item->quantity,3) }} {{ $item->product->unit?->short_name }}</td><td class="px-3 py-3 text-right">৳{{ number_format((float)($item->unit_cost??0),2) }}</td></tr>@endforeach</tbody></table></div></div></div>@endif
</div>
