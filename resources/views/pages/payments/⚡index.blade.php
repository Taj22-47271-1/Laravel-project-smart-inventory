<?php

use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\PaymentService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $targetType = 'sale';
    public string $targetId = '';
    public string $paymentDate = '';
    public string $amount = '';
    public string $method = Payment::METHOD_CASH;
    public string $reference = '';
    public string $notes = '';
    public string $search = '';

    public function mount(): void
    {
        $this->paymentDate = now()->format('Y-m-d');
    }

    public function updatedTargetType(): void
    {
        $this->targetId = '';
        $this->amount = '';
        unset($this->targets, $this->selectedTarget);
    }

    public function updatedTargetId(): void
    {
        $this->amount = $this->selectedTarget
            ? (string) $this->selectedTarget->due_amount
            : '';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function targets()
    {
        if ($this->targetType === 'purchase') {
            return Purchase::query()
                ->whereIn('status', [Purchase::STATUS_APPROVED, Purchase::STATUS_RECEIVED])
                ->where('due_amount', '>', 0)
                ->with('supplier:id,name,company_name')
                ->latest('purchase_date')
                ->limit(100)
                ->get();
        }

        return Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('due_amount', '>', 0)
            ->with('customer:id,name')
            ->latest('sale_date')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function selectedTarget(): Sale|Purchase|null
    {
        if ($this->targetId === '') {
            return null;
        }

        return $this->targetType === 'purchase'
            ? Purchase::with('supplier')->findOrFail((int) $this->targetId)
            : Sale::with('customer')->findOrFail((int) $this->targetId);
    }

    #[Computed]
    public function payments()
    {
        $search = trim($this->search);

        return Payment::query()
            ->with(['payable', 'createdBy'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('method', 'like', "%{$search}%");
            }))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(15);
    }

    public function recordPayment(): void
    {
        abort_unless(auth()->user()->can('create payments'), 403);

        $validated = $this->validate([
            'targetType' => ['required', 'in:sale,purchase'],
            'targetId' => ['required', 'integer'],
            'paymentDate' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank,card,mobile_banking'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            if ($validated['targetType'] === 'purchase') {
                $payment = app(PaymentService::class)->recordPurchasePayment(
                    Purchase::findOrFail((int) $validated['targetId']),
                    (float) $validated['amount'],
                    $validated['paymentDate'],
                    $validated['method'],
                    $validated['reference'] ?: null,
                    $validated['notes'] ?: null,
                    auth()->user()
                );
            } else {
                $payment = app(PaymentService::class)->recordSalePayment(
                    Sale::findOrFail((int) $validated['targetId']),
                    (float) $validated['amount'],
                    $validated['paymentDate'],
                    $validated['method'],
                    $validated['reference'] ?: null,
                    $validated['notes'] ?: null,
                    auth()->user()
                );
            }

            session()->flash('success', 'Payment recorded: '.$payment->payment_number);
            $this->reset(['targetId', 'amount', 'reference', 'notes']);
            $this->paymentDate = now()->format('Y-m-d');
            unset($this->targets, $this->selectedTarget, $this->payments);
        } catch (ValidationException $exception) {
            $this->addError('payment', collect($exception->errors())->flatten()->first());
        }
    }

    public function payableNumber(Payment $payment): string
    {
        return $payment->payable instanceof Sale
            ? $payment->payable->sale_number
            : ($payment->payable instanceof Purchase
                ? $payment->payable->purchase_number
                : class_basename($payment->payable_type).' #'.$payment->payable_id);
    }
};
?>

<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">Payments & Due Collection</h1><p class="text-sm text-gray-500">Receive customer dues and pay supplier dues.</p></div>
    @if (session()->has('success')) <div class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
    @error('payment') <div class="rounded-lg bg-red-50 p-4 text-red-700">{{ $message }}</div> @enderror

    @can('create payments')
        <form wire:submit="recordPayment" class="space-y-4 rounded-xl border bg-white p-5 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Record Payment</h2>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div><label class="mb-2 block text-sm font-medium">Type</label><select wire:model.live="targetType" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"><option value="sale">Customer Receipt</option><option value="purchase">Supplier Payment</option></select></div>
                <div><label class="mb-2 block text-sm font-medium">Document</label><select wire:model.live="targetId" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"><option value="">Select document</option>@foreach ($this->targets as $target)<option value="{{ $target->id }}">{{ $target instanceof Sale ? $target->sale_number : $target->purchase_number }} — Due ৳{{ number_format((float)$target->due_amount,2) }}</option>@endforeach</select></div>
                <div><label class="mb-2 block text-sm font-medium">Payment Date</label><input type="date" wire:model="paymentDate" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"></div>
                <div><label class="mb-2 block text-sm font-medium">Amount</label><input type="number" min="0.01" step="0.01" wire:model="amount" class="w-full rounded-lg border px-3 py-2.5 text-right dark:bg-gray-800"></div>
                <div><label class="mb-2 block text-sm font-medium">Method</label><select wire:model="method" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800"><option value="cash">Cash</option><option value="bank">Bank</option><option value="card">Card</option><option value="mobile_banking">Mobile Banking</option></select></div>
                <div><label class="mb-2 block text-sm font-medium">Reference</label><input wire:model="reference" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800" placeholder="Transaction ID"></div>
                <div class="md:col-span-2"><label class="mb-2 block text-sm font-medium">Notes</label><input wire:model="notes" class="w-full rounded-lg border px-3 py-2.5 dark:bg-gray-800" placeholder="Optional notes"></div>
            </div>
            @if ($this->selectedTarget)<div class="rounded-lg bg-blue-50 p-3 text-sm text-blue-800">Grand total: ৳{{ number_format((float)$this->selectedTarget->grand_total,2) }} | Paid: ৳{{ number_format((float)$this->selectedTarget->paid_amount,2) }} | Due: ৳{{ number_format((float)$this->selectedTarget->due_amount,2) }}</div>@endif
            <div class="flex justify-end"><button type="submit" class="rounded-lg bg-green-600 px-5 py-2.5 font-semibold text-white">Record Payment</button></div>
        </form>
    @endcan

    <div class="rounded-xl border bg-white dark:bg-gray-900">
        <div class="border-b p-5"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-semibold">Payment History</h2><p class="text-sm text-gray-500">All customer receipts and supplier payments.</p></div><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search payment..." class="rounded-lg border px-3 py-2.5 dark:bg-gray-800"></div></div>
        <div class="overflow-x-auto"><table class="min-w-[1000px] w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr><th class="px-4 py-3">Payment</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Document</th><th class="px-4 py-3">Direction</th><th class="px-4 py-3">Method</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Created By</th></tr></thead><tbody class="divide-y">@forelse ($this->payments as $payment)<tr wire:key="payment-{{ $payment->id }}"><td class="px-4 py-4 font-semibold">{{ $payment->payment_number }}</td><td class="px-4 py-4">{{ $payment->payment_date->format('d M Y') }}</td><td class="px-4 py-4">{{ $this->payableNumber($payment) }}</td><td class="px-4 py-4 capitalize">{{ $payment->direction }}</td><td class="px-4 py-4 capitalize">{{ str_replace('_',' ',$payment->method) }}</td><td class="px-4 py-4 text-right font-semibold">৳{{ number_format((float)$payment->amount,2) }}</td><td class="px-4 py-4">{{ $payment->reference ?? 'N/A' }}</td><td class="px-4 py-4">{{ $payment->createdBy->name }}</td></tr>@empty<tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No payments found.</td></tr>@endforelse</tbody></table></div><div class="border-t p-4">{{ $this->payments->links() }}</div>
    </div>
</div>
