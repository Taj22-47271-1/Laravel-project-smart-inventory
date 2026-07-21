<?php

use App\Models\Supplier;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $editingSupplierId = null;

    public string $supplier_code = '';

    public string $name = '';

    public string $company_name = '';

    public string $email = '';

    public string $phone = '';

    public string $alternate_phone = '';

    public string $tax_number = '';

    public string $address = '';

    public string $city = '';

    public string $country = 'Bangladesh';

    public string $opening_balance = '0.00';

    public string $notes = '';

    public bool $status = true;

    public string $search = '';

    public string $statusFilter = '';

    /**
     * Search পরিবর্তন হলে pagination reset করবে।
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Status filter পরিবর্তন হলে pagination reset করবে।
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * নতুন supplier তৈরি অথবা existing supplier update করবে।
     */
    public function save(): void
    {
        $isEditing = $this->editingSupplierId !== null;

        abort_unless(
            auth()->user()?->can(
                $isEditing ? 'edit suppliers' : 'create suppliers'
            ),
            403
        );

        $supplier = $isEditing
            ? Supplier::findOrFail($this->editingSupplierId)
            : new Supplier();

        $supplierCodeRule = Rule::unique(
            'suppliers',
            'supplier_code'
        );

        $emailRule = Rule::unique(
            'suppliers',
            'email'
        );

        if ($isEditing) {
            $supplierCodeRule->ignore($supplier);
            $emailRule->ignore($supplier);
        }

        $validated = $this->validate([
            'supplier_code' => [
                'required',
                'string',
                'max:50',
                $supplierCodeRule,
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'company_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'email' => [
                'nullable',
                'email',
                'max:150',
                $emailRule,
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'alternate_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'tax_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'city' => [
                'nullable',
                'string',
                'max:100',
            ],

            'country' => [
                'required',
                'string',
                'max:100',
            ],

            'opening_balance' => [
                'required',
                'numeric',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'status' => [
                'boolean',
            ],
        ]);

        $supplier->fill([
            'supplier_code' => Str::upper(
                trim($validated['supplier_code'])
            ),

            'name' => trim($validated['name']),

            'company_name' => filled(
                $validated['company_name'] ?? null
            )
                ? trim($validated['company_name'])
                : null,

            'email' => filled($validated['email'] ?? null)
                ? Str::lower(trim($validated['email']))
                : null,

            'phone' => filled($validated['phone'] ?? null)
                ? trim($validated['phone'])
                : null,

            'alternate_phone' => filled(
                $validated['alternate_phone'] ?? null
            )
                ? trim($validated['alternate_phone'])
                : null,

            'tax_number' => filled(
                $validated['tax_number'] ?? null
            )
                ? trim($validated['tax_number'])
                : null,

            'address' => filled($validated['address'] ?? null)
                ? trim($validated['address'])
                : null,

            'city' => filled($validated['city'] ?? null)
                ? trim($validated['city'])
                : null,

            'country' => trim($validated['country']),

            'opening_balance' => $validated['opening_balance'],

            'notes' => filled($validated['notes'] ?? null)
                ? trim($validated['notes'])
                : null,

            'status' => $validated['status'],
        ]);

        $supplier->save();

        $message = $isEditing
            ? 'Supplier updated successfully.'
            : 'Supplier created successfully.';

        $this->resetForm();

        unset($this->suppliers);

        session()->flash('success', $message);
    }

    /**
     * Supplier data form-এ দেখাবে।
     */
    public function editSupplier(int $supplierId): void
    {
        abort_unless(
            auth()->user()?->can('edit suppliers'),
            403
        );

        $supplier = Supplier::findOrFail($supplierId);

        $this->editingSupplierId = $supplier->id;
        $this->supplier_code = $supplier->supplier_code;
        $this->name = $supplier->name;
        $this->company_name = $supplier->company_name ?? '';
        $this->email = $supplier->email ?? '';
        $this->phone = $supplier->phone ?? '';
        $this->alternate_phone =
            $supplier->alternate_phone ?? '';
        $this->tax_number = $supplier->tax_number ?? '';
        $this->address = $supplier->address ?? '';
        $this->city = $supplier->city ?? '';
        $this->country = $supplier->country;
        $this->opening_balance =
            (string) $supplier->opening_balance;
        $this->notes = $supplier->notes ?? '';
        $this->status = $supplier->status;

        $this->resetValidation();
    }

    /**
     * Edit mode বন্ধ করবে।
     */
    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    /**
     * Supplier soft delete করবে।
     */
    public function deleteSupplier(int $supplierId): void
    {
        abort_unless(
            auth()->user()?->can('delete suppliers'),
            403
        );

        $supplier = Supplier::findOrFail($supplierId);

        $supplier->delete();

        if ($this->editingSupplierId === $supplierId) {
            $this->resetForm();
        }

        unset($this->suppliers);

        session()->flash(
            'success',
            'Supplier deleted successfully.'
        );
    }

    /**
     * Supplier form reset করবে।
     */
    private function resetForm(): void
    {
        $this->editingSupplierId = null;
        $this->supplier_code = '';
        $this->name = '';
        $this->company_name = '';
        $this->email = '';
        $this->phone = '';
        $this->alternate_phone = '';
        $this->tax_number = '';
        $this->address = '';
        $this->city = '';
        $this->country = 'Bangladesh';
        $this->opening_balance = '0.00';
        $this->notes = '';
        $this->status = true;

        $this->resetValidation();
    }

    /**
     * Supplier list দেখাবে।
     */
    #[Computed]
    public function suppliers()
    {
        $search = trim($this->search);

        return Supplier::query()
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'supplier_code',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
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
                                    'email',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    '%'.$search.'%'
                                );
                        }
                    );
                }
            )
            ->when(
                $this->statusFilter !== '',
                fn ($query) => $query->where(
                    'status',
                    $this->statusFilter === '1'
                )
            )
            ->latest()
            ->paginate(10);
    }
};

?>

<div class="space-y-6">
    {{-- Page heading --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Supplier Management
        </h1>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Create and manage product suppliers.
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

    {{-- Supplier form --}}
    @if (
        auth()->user()->can('create suppliers') ||
        auth()->user()->can('edit suppliers')
    )
        <div
            class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ $editingSupplierId
                            ? 'Edit Supplier'
                            : 'Add New Supplier' }}
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Enter the supplier information below.
                    </p>
                </div>

                @if ($editingSupplierId)
                    <span
                        class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                    >
                        Editing
                    </span>
                @endif
            </div>

            <form wire:submit="save" class="mt-6 space-y-6">
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {{-- Supplier code --}}
                    <div>
                        <label
                            for="supplier_code"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Supplier Code
                        </label>

                        <input
                            id="supplier_code"
                            type="text"
                            wire:model="supplier_code"
                            placeholder="Example: SUP-001"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('supplier_code')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Supplier name --}}
                    <div>
                        <label
                            for="name"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Supplier Name
                        </label>

                        <input
                            id="name"
                            type="text"
                            wire:model="name"
                            placeholder="Example: Rahim Ahmed"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('name')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Company --}}
                    <div>
                        <label
                            for="company_name"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Company Name
                        </label>

                        <input
                            id="company_name"
                            type="text"
                            wire:model="company_name"
                            placeholder="Example: Tech Distribution Ltd."
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('company_name')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label
                            for="email"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Email Address
                        </label>

                        <input
                            id="email"
                            type="email"
                            wire:model="email"
                            placeholder="supplier@example.com"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label
                            for="phone"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Phone Number
                        </label>

                        <input
                            id="phone"
                            type="text"
                            wire:model="phone"
                            placeholder="Example: 01700000000"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('phone')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Alternate phone --}}
                    <div>
                        <label
                            for="alternate_phone"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Alternate Phone
                        </label>

                        <input
                            id="alternate_phone"
                            type="text"
                            wire:model="alternate_phone"
                            placeholder="Optional phone number"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('alternate_phone')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Tax number --}}
                    <div>
                        <label
                            for="tax_number"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Tax/VAT Number
                        </label>

                        <input
                            id="tax_number"
                            type="text"
                            wire:model="tax_number"
                            placeholder="Optional tax number"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('tax_number')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- City --}}
                    <div>
                        <label
                            for="city"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            City
                        </label>

                        <input
                            id="city"
                            type="text"
                            wire:model="city"
                            placeholder="Example: Dhaka"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('city')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Country --}}
                    <div>
                        <label
                            for="country"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Country
                        </label>

                        <input
                            id="country"
                            type="text"
                            wire:model="country"
                            placeholder="Example: Bangladesh"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('country')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Opening balance --}}
                    <div>
                        <label
                            for="opening_balance"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Opening Balance
                        </label>

                        <input
                            id="opening_balance"
                            type="number"
                            min="0"
                            step="0.01"
                            wire:model="opening_balance"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('opening_balance')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                {{-- Address --}}
                <div>
                    <label
                        for="address"
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Address
                    </label>

                    <textarea
                        id="address"
                        wire:model="address"
                        rows="3"
                        placeholder="Enter supplier address"
                        class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                    ></textarea>

                    @error('address')
                        <p class="mt-1.5 text-sm text-red-600">
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
                        rows="3"
                        placeholder="Optional supplier notes"
                        class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                    ></textarea>

                    @error('notes')
                        <p class="mt-1.5 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Status --}}
                <label class="flex cursor-pointer items-center gap-3">
                    <input
                        type="checkbox"
                        wire:model="status"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >

                    <span
                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Active supplier
                    </span>
                </label>

                {{-- Buttons --}}
                <div class="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="save">
                            {{ $editingSupplierId
                                ? 'Update Supplier'
                                : 'Save Supplier' }}
                        </span>

                        <span wire:loading wire:target="save">
                            Saving...
                        </span>
                    </button>

                    @if ($editingSupplierId)
                        <button
                            type="button"
                            wire:click="cancelEdit"
                            wire:loading.attr="disabled"
                            class="rounded-lg border border-gray-300 px-6 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            Cancel
                        </button>
                    @endif
                </div>
            </form>
        </div>
    @endif

    {{-- Supplier list --}}
    <div
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="border-b border-gray-200 p-5 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Supplier List
            </h2>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Search and manage all suppliers.
            </p>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search code, name, company, email or phone..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                >

                <select
                    wire:model.live="statusFilter"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                >
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead
                    class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                >
                    <tr>
                        <th class="px-5 py-3">Supplier</th>
                        <th class="px-5 py-3">Company</th>
                        <th class="px-5 py-3">Contact</th>
                        <th class="px-5 py-3">Location</th>
                        <th class="px-5 py-3">Opening Balance</th>
                        <th class="px-5 py-3">Status</th>

                        @if (
                            auth()->user()->can('edit suppliers') ||
                            auth()->user()->can('delete suppliers')
                        )
                            <th class="px-5 py-3 text-right">
                                Actions
                            </th>
                        @endif
                    </tr>
                </thead>

                <tbody
                    class="divide-y divide-gray-200 dark:divide-gray-700"
                >
                    @forelse ($this->suppliers as $supplier)
                        <tr
                            wire:key="supplier-{{ $supplier->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ $supplier->name }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $supplier->supplier_code }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $supplier->company_name ?? 'N/A' }}
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-gray-700 dark:text-gray-300"
                                >
                                    {{ $supplier->phone ?? 'No phone' }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $supplier->email ?? 'No email' }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-gray-600 dark:text-gray-300"
                            >
                                {{ $supplier->city
                                    ? $supplier->city.', '
                                    : '' }}
                                {{ $supplier->country }}
                            </td>

                            <td
                                class="px-5 py-4 font-medium text-gray-900 dark:text-white"
                            >
                                ৳{{ number_format(
                                    (float) $supplier->opening_balance,
                                    2
                                ) }}
                            </td>

                            <td class="px-5 py-4">
                                @if ($supplier->status)
                                    <span
                                        class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                    >
                                        Active
                                    </span>
                                @else
                                    <span
                                        class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        Inactive
                                    </span>
                                @endif
                            </td>

                            @if (
                                auth()->user()->can('edit suppliers') ||
                                auth()->user()->can('delete suppliers')
                            )
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        @can('edit suppliers')
                                            <button
                                                type="button"
                                                wire:click="editSupplier({{ $supplier->id }})"
                                                wire:loading.attr="disabled"
                                                class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-950 dark:text-amber-300"
                                            >
                                                Edit
                                            </button>
                                        @endcan

                                        @can('delete suppliers')
                                            <button
                                                type="button"
                                                wire:click="deleteSupplier({{ $supplier->id }})"
                                                wire:confirm="Are you sure you want to delete this supplier?"
                                                wire:loading.attr="disabled"
                                                class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-100 disabled:opacity-50 dark:bg-red-950 dark:text-red-300"
                                            >
                                                Delete
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="{{ auth()->user()->canAny([
                                    'edit suppliers',
                                    'delete suppliers',
                                ]) ? 7 : 6 }}"
                                class="px-5 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                No suppliers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->suppliers->hasPages())
            <div
                class="border-t border-gray-200 p-4 dark:border-gray-700"
            >
                {{ $this->suppliers->links() }}
            </div>
        @endif
    </div>
</div>