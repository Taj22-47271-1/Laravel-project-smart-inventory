<?php

use App\Models\Brand;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $editingBrandId = null;

    public string $name = '';

    public string $description = '';

    public bool $status = true;

    public string $search = '';

    /**
     * Search পরিবর্তন হলে pagination reset করবে।
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * নতুন brand তৈরি অথবা existing brand update করবে।
     */
    public function save(): void
    {
        $isEditing = $this->editingBrandId !== null;

        abort_unless(
            auth()->user()?->can(
                $isEditing ? 'edit brands' : 'create brands'
            ),
            403
        );

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('brands', 'name')
                    ->ignore($this->editingBrandId),
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

        $brand = $isEditing
            ? Brand::findOrFail($this->editingBrandId)
            : new Brand();

        $brand->fill([
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug(
                $validated['name'],
                $brand->exists ? $brand->id : null
            ),
            'description' => $validated['description'] ?: null,
            'status' => $validated['status'],
        ]);

        $brand->save();

        $message = $isEditing
            ? 'Brand updated successfully.'
            : 'Brand created successfully.';

        $this->resetForm();

        unset($this->brands);

        session()->flash('success', $message);
    }

    /**
     * Edit করার জন্য brand data form-এ দেখাবে।
     */
    public function editBrand(int $brandId): void
    {
        abort_unless(
            auth()->user()?->can('edit brands'),
            403
        );

        $brand = Brand::findOrFail($brandId);

        $this->editingBrandId = $brand->id;
        $this->name = $brand->name;
        $this->description = $brand->description ?? '';
        $this->status = $brand->status;

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
     * Brand soft delete করবে।
     */
    public function deleteBrand(int $brandId): void
    {
        abort_unless(
            auth()->user()?->can('delete brands'),
            403
        );

        $brand = Brand::findOrFail($brandId);

        $brand->delete();

        if ($this->editingBrandId === $brandId) {
            $this->resetForm();
        }

        unset($this->brands);

        session()->flash(
            'success',
            'Brand deleted successfully.'
        );
    }

    /**
     * Unique slug তৈরি করবে।
     */
    private function generateUniqueSlug(
        string $name,
        ?int $ignoreBrandId = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'brand-'.Str::lower(Str::random(6));
        }

        $slug = $baseSlug;
        $number = 1;

        while (
            Brand::withTrashed()
                ->where('slug', $slug)
                ->when(
                    $ignoreBrandId !== null,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $ignoreBrandId
                    )
                )
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$number;
            $number++;
        }

        return $slug;
    }

    /**
     * Form reset করবে।
     */
    private function resetForm(): void
    {
        $this->editingBrandId = null;
        $this->name = '';
        $this->description = '';
        $this->status = true;

        $this->resetValidation();
    }

    /**
     * Brand list দেখাবে।
     */
    #[Computed]
    public function brands()
    {
        return Brand::query()
            ->when(
                trim($this->search) !== '',
                fn ($query) => $query->where(
                    'name',
                    'like',
                    '%'.trim($this->search).'%'
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
            Brand Management
        </h1>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Create, edit and manage product brands.
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
        {{-- Brand form --}}
        @if (
            auth()->user()->can('create brands') ||
            auth()->user()->can('edit brands')
        )
            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $editingBrandId ? 'Edit Brand' : 'Add New Brand' }}
                    </h2>

                    @if ($editingBrandId)
                        <span
                            class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                        >
                            Editing
                        </span>
                    @endif
                </div>

                <form wire:submit="save" class="mt-5 space-y-5">
                    {{-- Brand name --}}
                    <div>
                        <label
                            for="name"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Brand Name
                        </label>

                        <input
                            id="name"
                            type="text"
                            wire:model="name"
                            placeholder="Example: Samsung"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('name')
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
                            Active brand
                        </span>
                    </label>

                    {{-- Buttons --}}
                    <div class="flex gap-3">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span wire:loading.remove wire:target="save">
                                {{ $editingBrandId
                                    ? 'Update Brand'
                                    : 'Save Brand' }}
                            </span>

                            <span wire:loading wire:target="save">
                                Saving...
                            </span>
                        </button>

                        @if ($editingBrandId)
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

        {{-- Brand list --}}
        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <div
                class="flex flex-col gap-4 border-b border-gray-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700"
            >
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Brand List
                    </h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        All available product brands
                    </p>
                </div>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search brand..."
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
                            <th class="px-5 py-3">Slug</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Created</th>

                            @if (
                                auth()->user()->can('edit brands') ||
                                auth()->user()->can('delete brands')
                            )
                                <th class="px-5 py-3 text-right">
                                    Actions
                                </th>
                            @endif
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->brands as $brand)
                            <tr
                                wire:key="brand-{{ $brand->id }}"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $brand->name }}
                                    </p>

                                    @if ($brand->description)
                                        <p class="mt-1 max-w-xs truncate text-xs text-gray-500">
                                            {{ $brand->description }}
                                        </p>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                    {{ $brand->slug }}
                                </td>

                                <td class="px-5 py-4">
                                    @if ($brand->status)
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
                                    {{ $brand->created_at->format('d M Y') }}
                                </td>

                                @if (
                                    auth()->user()->can('edit brands') ||
                                    auth()->user()->can('delete brands')
                                )
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            @can('edit brands')
                                                <button
                                                    type="button"
                                                    wire:click="editBrand({{ $brand->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="editBrand({{ $brand->id }})"
                                                    class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900"
                                                >
                                                    Edit
                                                </button>
                                            @endcan

                                            @can('delete brands')
                                                <button
                                                    type="button"
                                                    wire:click="deleteBrand({{ $brand->id }})"
                                                    wire:confirm="Are you sure you want to delete this brand?"
                                                    wire:loading.attr="disabled"
                                                    wire:target="deleteBrand({{ $brand->id }})"
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
                                        'edit brands',
                                        'delete brands',
                                    ]) ? 5 : 4 }}"
                                    class="px-5 py-12 text-center text-gray-500 dark:text-gray-400"
                                >
                                    No brands found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->brands->hasPages())
                <div class="border-t border-gray-200 p-4 dark:border-gray-700">
                    {{ $this->brands->links() }}
                </div>
            @endif
        </div>
    </div>
</div>