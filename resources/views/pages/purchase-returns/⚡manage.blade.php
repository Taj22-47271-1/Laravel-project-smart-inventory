<?php

use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
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
    public function open(int $id): void { $this->selectedId = $id; unset($this->selectedReturn); }
    public function close(): void { $this->selectedId = null; unset($this->selectedReturn); }

    public function complete(int $id): void
    {
        try {
            app(PurchaseReturnService::class)->complete(PurchaseReturn::findOrFail($id), auth()->user());
            session()->flash('success', 'Purchase return completed successfully.');
            unset($this->returns, $this->selectedReturn);
        } catch (ValidationException $exception) {
            $this->addError('workflow', collect($exception->errors())->flatten()->first());
        }
    }

    public function cancel(int $id): void
    {
        try {
            app(PurchaseReturnService::class)->cancel(PurchaseReturn::findOrFail($id), auth()->user());
            session()->flash('success', 'Purchase return cancelled successfully.');
            unset($this->returns, $this->selectedReturn);
        } catch (ValidationException $exception) {
            $this->addError('workflow', collect($exception->errors())->flatten()->first());
        }
    }

    #[Computed]
    public function returns()
    {
        $search = trim($this->search);

        return PurchaseReturn::query()
            ->with(['purchase.supplier', 'createdBy', 'completedBy'])
            ->withCount('items')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('return_number', 'like', "%{$search}%")
                    ->orWhereHas('purchase', fn ($query) => $query->where('purchase_number', 'like', "%{$search}%"))
                    ->orWhereHas('purchase.supplier', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"));
            }))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->latest('return_date')
            ->paginate(10);
    }

    #[Computed]
    public function selectedReturn(): ?PurchaseReturn
    {
        return $this->selectedId
            ? PurchaseReturn::with(['purchase.supplier', 'items.product.unit', 'createdBy', 'completedBy'])->findOrFail($this->selectedId)
            : null;
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h1 class="text-2xl font-bold">Purchase Returns</h1><p class="text-sm text-gray-500">Manage supplier returns.</p></div><a href="{{ route('purchase-returns.index') }}" wire:navigate class="rounded-lg bg-blue-600 px-4 py-2.5 font-semibold text-white">+ Create Return</a></div>
    @if (session()->has('success')) <div class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @error('workflow') <div class="rounded-lg bg-red-50 p-4 text-red-700">{{ $message }}</div> @enderror
    <div class="grid gap-4 rounded-xl border bg-white p-5 md:grid-cols-2 dark:bg-gray-900"><input type="search" wire:model.live.debounce.300ms="search" placeholder="Return, purchase or supplier..." class="rounded-lg border px-3 py-2.5 dark:bg-gray-800"><select wire:model.live="status" class="rounded-lg border px-3 py-2.5 dark:bg-gray-800"><option value="">All statuses</option><option value="draft">Draft</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
    <div class="overflow-hidden rounded-xl border bg-white dark:bg-gray-900"><div class="overflow-x-auto"><table class="min-w-[1050px] w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-4 py-3">Return</th><th class="px-4 py-3">Purchase</th><th class="px-4 py-3">Supplier</th><th class="px-4 py-3">Date</th><th class="px-4 py-3 text-center">Items</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y">@forelse ($this->returns as $return)<tr wire:key="purchase-return-{{ $return->id }}"><td class="px-4 py-4 font-semibold">{{ $return->return_number }}</td><td class="px-4 py-4">{{ $return->purchase->purchase_number }}</td><td class="px-4 py-4">{{ $return->purchase->supplier->company_name ?? $return->purchase->supplier->name }}</td><td class="px-4 py-4">{{ $return->return_date->format('d M Y') }}</td><td class="px-4 py-4 text-center">{{ $return->items_count }}</td><td class="px-4 py-4 text-right font-semibold">৳{{ number_format((float)$return->total_amount,2) }}</td><td class="px-4 py-4 capitalize">{{ $return->status }}</td><td class="px-4 py-4"><div class="flex justify-end gap-2"><button wire:click="open({{ $return->id }})" class="rounded bg-gray-100 px-3 py-2">View</button>@if ($return->isDraft()) @can('complete purchase returns')<button wire:click="complete({{ $return->id }})" wire:confirm="Complete and reduce stock?" class="rounded bg-green-100 px-3 py-2 text-green-700">Complete</button>@endcan @can('cancel purchase returns')<button wire:click="cancel({{ $return->id }})" wire:confirm="Cancel this return?" class="rounded bg-red-100 px-3 py-2 text-red-700">Cancel</button>@endcan @endif</div></td></tr>@empty<tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No purchase returns found.</td></tr>@endforelse</tbody></table></div><div class="border-t p-4">{{ $this->returns->links() }}</div></div>
    @if ($this->selectedReturn)<div wire:click.self="close" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"><div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-6 dark:bg-gray-900"><div class="flex justify-between"><div><h2 class="text-xl font-bold">{{ $this->selectedReturn->return_number }}</h2><p class="text-sm text-gray-500">{{ $this->selectedReturn->purchase->purchase_number }}</p></div><button wire:click="close" class="rounded border px-3 py-2">Close</button></div><div class="mt-5 overflow-x-auto"><table class="w-full min-w-[700px] text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-3 py-2 text-left">Product</th><th class="px-3 py-2 text-right">Qty</th><th class="px-3 py-2 text-right">Cost</th><th class="px-3 py-2 text-right">Total</th></tr></thead><tbody class="divide-y">@foreach ($this->selectedReturn->items as $item)<tr><td class="px-3 py-3">{{ $item->product->name }}</td><td class="px-3 py-3 text-right">{{ number_format((float)$item->quantity,3) }} {{ $item->product->unit?->short_name }}</td><td class="px-3 py-3 text-right">৳{{ number_format((float)$item->unit_cost,2) }}</td><td class="px-3 py-3 text-right">৳{{ number_format((float)$item->line_total,2) }}</td></tr>@endforeach</tbody></table></div></div></div>@endif
</div>
