<?php

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public ?int $editingCategoryId = null;

    public string $name = '';

    public string $description = '';

    public bool $status = true;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Create or update category.
     */
    public function save(): void
    {
        $isEditing = $this->editingCategoryId !== null;

        abort_unless(
            auth()->user()?->can(
                $isEditing ? 'edit categories' : 'create categories'
            ),
            403
        );

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->ignore($this->editingCategoryId),
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

        $category = $isEditing
            ? Category::findOrFail($this->editingCategoryId)
            : new Category();

        $category->fill([
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug(
                $validated['name'],
                $category->exists ? $category->id : null
            ),
            'description' => $validated['description'] ?: null,
            'status' => $validated['status'],
        ]);

        $category->save();

        $message = $isEditing
            ? 'Category updated successfully.'
            : 'Category created successfully.';

        $this->resetForm();

        unset($this->categories);

        session()->flash('success', $message);
    }

    /**
     * Load category data into the form.
     */
    public function editCategory(int $categoryId): void
    {
        abort_unless(
            auth()->user()?->can('edit categories'),
            403
        );

        $category = Category::findOrFail($categoryId);

        $this->editingCategoryId = $category->id;
        $this->name = $category->name;
        $this->description = $category->description ?? '';
        $this->status = $category->status;

        $this->resetValidation();
    }

    /**
     * Cancel category editing.
     */
    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    /**
     * Soft delete category.
     */
    public function deleteCategory(int $categoryId): void
    {
        abort_unless(
            auth()->user()?->can('delete categories'),
            403
        );

        $category = Category::findOrFail($categoryId);

        $category->delete();

        if ($this->editingCategoryId === $categoryId) {
            $this->resetForm();
        }

        unset($this->categories);

        session()->flash(
            'success',
            'Category deleted successfully.'
        );
    }

    /**
     * Generate a unique category slug.
     */
    private function generateUniqueSlug(
        string $name,
        ?int $ignoreCategoryId = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'category-'.Str::lower(Str::random(6));
        }

        $slug = $baseSlug;
        $number = 1;

        while (
            Category::withTrashed()
                ->where('slug', $slug)
                ->when(
                    $ignoreCategoryId !== null,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $ignoreCategoryId
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
     * Reset category form.
     */
    private function resetForm(): void
    {
        $this->editingCategoryId = null;
        $this->name = '';
        $this->description = '';
        $this->status = true;

        $this->resetValidation();
    }

    /**
     * Get category list.
     */
    #[Computed]
    public function categories()
    {
        return Category::query()
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
            Category Management
        </h1>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Create, edit and manage product categories.
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
        {{-- Category form --}}
        @if (
            auth()->user()->can('create categories') ||
            auth()->user()->can('edit categories')
        )
            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $editingCategoryId ? 'Edit Category' : 'Add New Category' }}
                    </h2>

                    @if ($editingCategoryId)
                        <span
                            class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                        >
                            Editing
                        </span>
                    @endif
                </div>

                <form wire:submit="save" class="mt-5 space-y-5">
                    {{-- Category name --}}
                    <div>
                        <label
                            for="name"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Category Name
                        </label>

                        <input
                            id="name"
                            type="text"
                            wire:model="name"
                            placeholder="Example: Electronics"
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
                            Active category
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
                                {{ $editingCategoryId
                                    ? 'Update Category'
                                    : 'Save Category' }}
                            </span>

                            <span wire:loading wire:target="save">
                                Saving...
                            </span>
                        </button>

                        @if ($editingCategoryId)
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

        {{-- Category list --}}
        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <div
                class="flex flex-col gap-4 border-b border-gray-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700"
            >
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Category List
                    </h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        All available product categories
                    </p>
                </div>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search category..."
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
                                auth()->user()->can('edit categories') ||
                                auth()->user()->can('delete categories')
                            )
                                <th class="px-5 py-3 text-right">
                                    Actions
                                </th>
                            @endif
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->categories as $category)
                            <tr
                                wire:key="category-{{ $category->id }}"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $category->name }}
                                    </p>

                                    @if ($category->description)
                                        <p class="mt-1 max-w-xs truncate text-xs text-gray-500">
                                            {{ $category->description }}
                                        </p>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                    {{ $category->slug }}
                                </td>

                                <td class="px-5 py-4">
                                    @if ($category->status)
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
                                    {{ $category->created_at->format('d M Y') }}
                                </td>

                                @if (
                                    auth()->user()->can('edit categories') ||
                                    auth()->user()->can('delete categories')
                                )
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            @can('edit categories')
                                                <button
                                                    type="button"
                                                    wire:click="editCategory({{ $category->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="editCategory({{ $category->id }})"
                                                    class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900"
                                                >
                                                    Edit
                                                </button>
                                            @endcan

                                            @can('delete categories')
                                                <button
                                                    type="button"
                                                    wire:click="deleteCategory({{ $category->id }})"
                                                    wire:confirm="Are you sure you want to delete this category?"
                                                    wire:loading.attr="disabled"
                                                    wire:target="deleteCategory({{ $category->id }})"
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
                                        'edit categories',
                                        'delete categories',
                                    ]) ? 5 : 4 }}"
                                    class="px-5 py-12 text-center text-gray-500 dark:text-gray-400"
                                >
                                    No categories found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->categories->hasPages())
                <div class="border-t border-gray-200 p-4 dark:border-gray-700">
                    {{ $this->categories->links() }}
                </div>
            @endif
        </div>
    </div>
</div>