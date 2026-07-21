<?php

use App\Models\Unit;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $editingUnitId = null;

    public string $name = '';

    public string $short_name = '';

    public string $description = '';

    public bool $status = true;

    public string $search = '';

    /**
     * Search পরিবর্তন হলে প্রথম page-এ ফিরে যাবে।
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * নতুন Unit তৈরি অথবা পুরোনো Unit update করবে।
     */
    public function save(): void
    {
        $isEditing = $this->editingUnitId !== null;

        abort_unless(
            auth()->user()?->can(
                $isEditing ? 'edit units' : 'create units'
            ),
            403
        );

        $unit = $isEditing
            ? Unit::findOrFail($this->editingUnitId)
            : new Unit();

        $nameRule = Rule::unique('units', 'name');
        $shortNameRule = Rule::unique('units', 'short_name');

        if ($isEditing) {
            $nameRule->ignore($unit);
            $shortNameRule->ignore($unit);
        }

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                $nameRule,
            ],

            'short_name' => [
                'required',
                'string',
                'max:20',
                $shortNameRule,
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'status' => [
                'boolean',
            ],
        ]);

        $unit->fill([
            'name' => trim($validated['name']),
            'short_name' => Str::lower(
                trim($validated['short_name'])
            ),
            'description' => filled($validated['description'])
                ? trim($validated['description'])
                : null,
            'status' => $validated['status'],
        ]);

        $unit->save();

        $message = $isEditing
            ? 'Unit updated successfully.'
            : 'Unit created successfully.';

        $this->resetForm();

        unset($this->units);

        session()->flash('success', $message);
    }

    /**
     * Edit করার জন্য Unit-এর তথ্য form-এ দেখাবে।
     */
    public function editUnit(int $unitId): void
    {
        abort_unless(
            auth()->user()?->can('edit units'),
            403
        );

        $unit = Unit::findOrFail($unitId);

        $this->editingUnitId = $unit->id;
        $this->name = $unit->name;
        $this->short_name = $unit->short_name;
        $this->description = $unit->description ?? '';
        $this->status = $unit->status;

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
     * Unit soft delete করবে।
     */
    public function deleteUnit(int $unitId): void
    {
        abort_unless(
            auth()->user()?->can('delete units'),
            403
        );

        $unit = Unit::findOrFail($unitId);

        $unit->delete();

        if ($this->editingUnitId === $unitId) {
            $this->resetForm();
        }

        unset($this->units);

        session()->flash(
            'success',
            'Unit deleted successfully.'
        );
    }

    /**
     * Form-এর সব field reset করবে।
     */
    private function resetForm(): void
    {
        $this->editingUnitId = null;
        $this->name = '';
        $this->short_name = '';
        $this->description = '';
        $this->status = true;

        $this->resetValidation();
    }

    /**
     * Unit list দেখাবে।
     */
    #[Computed]
    public function units()
    {
        $search = trim($this->search);

        return Unit::query()
            ->when(
                $search !== '',
                fn ($query) => $query->where(
                    function ($query) use ($search): void {
                        $query
                            ->where(
                                'name',
                                'like',
                                '%'.$search.'%'
                            )
                            ->orWhere(
                                'short_name',
                                'like',
                                '%'.$search.'%'
                            );
                    }
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
            Unit Management
        </h1>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Create and manage product measurement units.
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

    <div class="grid gap-6 lg:grid-cols-[380px_minmax(0,1fr)]">
        {{-- Unit form --}}
        @if (
            auth()->user()->can('create units') ||
            auth()->user()->can('edit units')
        )
            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $editingUnitId ? 'Edit Unit' : 'Add New Unit' }}
                    </h2>

                    @if ($editingUnitId)
                        <span
                            class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                        >
                            Editing
                        </span>
                    @endif
                </div>

                <form wire:submit="save" class="mt-5 space-y-5">
                    {{-- Unit name --}}
                    <div>
                        <label
                            for="name"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Unit Name
                        </label>

                        <input
                            id="name"
                            type="text"
                            wire:model="name"
                            placeholder="Example: Piece"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('name')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Short name --}}
                    <div>
                        <label
                            for="short_name"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Short Name
                        </label>

                        <input
                            id="short_name"
                            type="text"
                            wire:model="short_name"
                            placeholder="Example: pcs"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('short_name')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Description --}}
                    <div>
                        <label
                            for="description"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Description
                        </label>

                        <textarea
                            id="description"
                            wire:model="description"
                            rows="4"
                            placeholder="Write a short description"
                            class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        ></textarea>

                        @error('description')
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

                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Active unit
                        </span>
                    </label>

                    {{-- Form buttons --}}
                    <div class="flex gap-3">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="save">
                                {{ $editingUnitId
                                    ? 'Update Unit'
                                    : 'Save Unit' }}
                            </span>

                            <span wire:loading wire:target="save">
                                Saving...
                            </span>
                        </button>

                        @if ($editingUnitId)
                            <button
                                type="button"
                                wire:click="cancelEdit"
                                wire:loading.attr="disabled"
                                class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 disabled:opacity-60 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                Cancel
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        @endif

        {{-- Unit list --}}
        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <div
                class="flex flex-col gap-4 border-b border-gray-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700"
            >
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Unit List
                    </h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        All available product measurement units
                    </p>
                </div>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search unit..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 sm:w-64 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                >
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                        <tr>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Short Name</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Created</th>

                            @if (
                                auth()->user()->can('edit units') ||
                                auth()->user()->can('delete units')
                            )
                                <th class="px-5 py-3 text-right">
                                    Actions
                                </th>
                            @endif
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->units as $unit)
                            <tr
                                wire:key="unit-{{ $unit->id }}"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $unit->name }}
                                    </p>

                                    @if ($unit->description)
                                        <p class="mt-1 max-w-xs truncate text-xs text-gray-500">
                                            {{ $unit->description }}
                                        </p>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-md bg-gray-100 px-2.5 py-1 font-mono text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    >
                                        {{ $unit->short_name }}
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    @if ($unit->status)
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

                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                    {{ $unit->created_at->format('d M Y') }}
                                </td>

                                @if (
                                    auth()->user()->can('edit units') ||
                                    auth()->user()->can('delete units')
                                )
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            @can('edit units')
                                                <button
                                                    type="button"
                                                    wire:click="editUnit({{ $unit->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="editUnit"
                                                    class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900"
                                                >
                                                    Edit
                                                </button>
                                            @endcan

                                            @can('delete units')
                                                <button
                                                    type="button"
                                                    wire:click="deleteUnit({{ $unit->id }})"
                                                    wire:confirm="Are you sure you want to delete this unit?"
                                                    wire:loading.attr="disabled"
                                                    wire:target="deleteUnit"
                                                    class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-100 disabled:opacity-50 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-900"
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
                                        'edit units',
                                        'delete units',
                                    ]) ? 5 : 4 }}"
                                    class="px-5 py-12 text-center text-gray-500 dark:text-gray-400"
                                >
                                    No units found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->units->hasPages())
                <div class="border-t border-gray-200 p-4 dark:border-gray-700">
                    {{ $this->units->links() }}
                </div>
            @endif
        </div>
    </div>
</div>