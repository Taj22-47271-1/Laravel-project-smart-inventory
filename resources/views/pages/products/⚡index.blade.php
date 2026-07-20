<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $editingProductId = null;

    public $category_id = '';

    public $brand_id = '';

    public $unit_id = '';

    public string $name = '';

    public string $sku = '';

    public string $barcode = '';

    public string $description = '';

    public string $purchase_price = '0.00';

    public string $selling_price = '0.00';

    public string $alert_quantity = '0';

    public bool $status = true;

    public $image = null;

    public ?string $existingImage = null;

    public bool $removeExistingImage = false;

    public int $imageInputKey = 0;

    public string $search = '';

    public string $categoryFilter = '';

    public string $statusFilter = '';

    /**
     * Search পরিবর্তন হলে pagination reset করবে।
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Category filter পরিবর্তন হলে pagination reset করবে।
     */
    public function updatedCategoryFilter(): void
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
     * নতুন product তৈরি অথবা existing product update করবে।
     */
    public function save(): void
    {
        $isEditing = $this->editingProductId !== null;

        abort_unless(
            auth()->user()?->can(
                $isEditing ? 'edit products' : 'create products'
            ),
            403
        );

        if ($this->brand_id === '') {
            $this->brand_id = null;
        }

        $product = $isEditing
            ? Product::findOrFail($this->editingProductId)
            : new Product();

        $skuRule = Rule::unique('products', 'sku');
        $barcodeRule = Rule::unique('products', 'barcode');

        if ($isEditing) {
            $skuRule->ignore($product);
            $barcodeRule->ignore($product);
        }

        $validated = $this->validate([
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'brand_id' => [
                'nullable',
                'integer',
                'exists:brands,id',
            ],

            'unit_id' => [
                'required',
                'integer',
                'exists:units,id',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'sku' => [
                'required',
                'string',
                'max:100',
                $skuRule,
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:100',
                $barcodeRule,
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'purchase_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'selling_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'alert_quantity' => [
                'required',
                'numeric',
                'min:0',
            ],

            'status' => [
                'boolean',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $imagePath = $product->image;

        /*
         * নতুন image থাকলে আগে সেটি store করবে।
         */
        if ($this->image) {
            $newImagePath = $this->image->store(
                'products',
                'public'
            );

            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            $imagePath = $newImagePath;
        } elseif ($this->removeExistingImage && $imagePath) {
            Storage::disk('public')->delete($imagePath);

            $imagePath = null;
        }

        $product->fill([
            'category_id' => (int) $validated['category_id'],

            'brand_id' => filled($validated['brand_id'] ?? null)
                ? (int) $validated['brand_id']
                : null,

            'unit_id' => (int) $validated['unit_id'],

            'name' => trim($validated['name']),

            'slug' => $this->generateUniqueSlug(
                $validated['name'],
                $product->exists ? $product->id : null
            ),

            'sku' => Str::upper(trim($validated['sku'])),

            'barcode' => filled($validated['barcode'] ?? null)
                ? trim($validated['barcode'])
                : null,

            'description' => filled($validated['description'] ?? null)
                ? trim($validated['description'])
                : null,

            'image' => $imagePath,

            'purchase_price' => $validated['purchase_price'],

            'selling_price' => $validated['selling_price'],

            'alert_quantity' => $validated['alert_quantity'],

            'status' => $validated['status'],
        ]);

        $product->save();

        $message = $isEditing
            ? 'Product updated successfully.'
            : 'Product created successfully.';

        $this->resetForm();

        unset($this->products);

        session()->flash('success', $message);
    }

    /**
     * Edit করার জন্য product data form-এ দেখাবে।
     */
    public function editProduct(int $productId): void
    {
        abort_unless(
            auth()->user()?->can('edit products'),
            403
        );

        $product = Product::findOrFail($productId);

        $this->editingProductId = $product->id;
        $this->category_id = (string) $product->category_id;
        $this->brand_id = $product->brand_id
            ? (string) $product->brand_id
            : '';
        $this->unit_id = (string) $product->unit_id;
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->barcode = $product->barcode ?? '';
        $this->description = $product->description ?? '';
        $this->purchase_price = (string) $product->purchase_price;
        $this->selling_price = (string) $product->selling_price;
        $this->alert_quantity = (string) $product->alert_quantity;
        $this->status = $product->status;
        $this->existingImage = $product->image;
        $this->removeExistingImage = false;
        $this->image = null;
        $this->imageInputKey++;

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
     * Existing image remove করার জন্য mark করবে।
     */
    public function removeCurrentImage(): void
    {
        $this->existingImage = null;
        $this->removeExistingImage = true;
    }

    /**
     * নতুন upload করা image cancel করবে।
     */
    public function clearNewImage(): void
    {
        $this->image = null;
        $this->imageInputKey++;
        $this->resetValidation('image');
    }

    /**
     * Product soft delete করবে।
     */
    public function deleteProduct(int $productId): void
    {
        abort_unless(
            auth()->user()?->can('delete products'),
            403
        );

        $product = Product::findOrFail($productId);

        $product->delete();

        if ($this->editingProductId === $productId) {
            $this->resetForm();
        }

        unset($this->products);

        session()->flash(
            'success',
            'Product deleted successfully.'
        );
    }

    /**
     * Unique product slug তৈরি করবে।
     */
    private function generateUniqueSlug(
        string $name,
        ?int $ignoreProductId = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'product-'.Str::lower(Str::random(6));
        }

        $slug = $baseSlug;
        $number = 1;

        while (
            Product::withTrashed()
                ->where('slug', $slug)
                ->when(
                    $ignoreProductId !== null,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $ignoreProductId
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
     * Product form reset করবে।
     */
    private function resetForm(): void
    {
        $this->editingProductId = null;
        $this->category_id = '';
        $this->brand_id = '';
        $this->unit_id = '';
        $this->name = '';
        $this->sku = '';
        $this->barcode = '';
        $this->description = '';
        $this->purchase_price = '0.00';
        $this->selling_price = '0.00';
        $this->alert_quantity = '0';
        $this->status = true;
        $this->image = null;
        $this->existingImage = null;
        $this->removeExistingImage = false;
        $this->imageInputKey++;

        $this->resetValidation();
    }

    /**
     * Category dropdown data।
     */
    #[Computed]
    public function categories()
    {
        return Category::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'status',
            ]);
    }

    /**
     * Brand dropdown data।
     */
    #[Computed]
    public function brands()
    {
        return Brand::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'status',
            ]);
    }

    /**
     * Unit dropdown data।
     */
    #[Computed]
    public function units()
    {
        return Unit::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'short_name',
                'status',
            ]);
    }

    /**
     * Product list।
     */
    #[Computed]
    public function products()
    {
        $search = trim($this->search);

        return Product::query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'unit:id,name,short_name',
            ])
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'sku',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'barcode',
                                    'like',
                                    '%'.$search.'%'
                                );
                        }
                    );
                }
            )
            ->when(
                $this->categoryFilter !== '',
                fn ($query) => $query->where(
                    'category_id',
                    $this->categoryFilter
                )
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
            Product Management
        </h1>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Create and manage inventory products.
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

    {{-- Product form --}}
    @if (
        auth()->user()->can('create products') ||
        auth()->user()->can('edit products')
    )
        <div
            class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $editingProductId
                            ? 'Edit Product'
                            : 'Add New Product' }}
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Enter the product information below.
                    </p>
                </div>

                @if ($editingProductId)
                    <span
                        class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                    >
                        Editing
                    </span>
                @endif
            </div>

            <form wire:submit="save" class="mt-6 space-y-6">
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    {{-- Product name --}}
                    <div>
                        <label
                            for="name"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Product Name
                        </label>

                        <input
                            id="name"
                            type="text"
                            wire:model="name"
                            placeholder="Example: Samsung Galaxy A55"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('name')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- SKU --}}
                    <div>
                        <label
                            for="sku"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            SKU
                        </label>

                        <input
                            id="sku"
                            type="text"
                            wire:model="sku"
                            placeholder="Example: SAM-A55-001"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('sku')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Barcode --}}
                    <div>
                        <label
                            for="barcode"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Barcode
                        </label>

                        <input
                            id="barcode"
                            type="text"
                            wire:model="barcode"
                            placeholder="Optional barcode"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('barcode')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Category --}}
                    <div>
                        <label
                            for="category_id"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Category
                        </label>

                        <select
                            id="category_id"
                            wire:model="category_id"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >
                            <option value="">Select category</option>

                            @foreach ($this->categories as $category)
                                <option value="{{ $category->id }}">
                                    {{ $category->name }}
                                    {{ $category->status ? '' : '(Inactive)' }}
                                </option>
                            @endforeach
                        </select>

                        @error('category_id')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Brand --}}
                    <div>
                        <label
                            for="brand_id"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Brand
                        </label>

                        <select
                            id="brand_id"
                            wire:model="brand_id"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >
                            <option value="">No brand</option>

                            @foreach ($this->brands as $brand)
                                <option value="{{ $brand->id }}">
                                    {{ $brand->name }}
                                    {{ $brand->status ? '' : '(Inactive)' }}
                                </option>
                            @endforeach
                        </select>

                        @error('brand_id')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Unit --}}
                    <div>
                        <label
                            for="unit_id"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Unit
                        </label>

                        <select
                            id="unit_id"
                            wire:model="unit_id"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >
                            <option value="">Select unit</option>

                            @foreach ($this->units as $unit)
                                <option value="{{ $unit->id }}">
                                    {{ $unit->name }}
                                    ({{ $unit->short_name }})
                                    {{ $unit->status ? '' : '(Inactive)' }}
                                </option>
                            @endforeach
                        </select>

                        @error('unit_id')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Purchase price --}}
                    <div>
                        <label
                            for="purchase_price"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Purchase Price
                        </label>

                        <input
                            id="purchase_price"
                            type="number"
                            min="0"
                            step="0.01"
                            wire:model="purchase_price"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('purchase_price')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Selling price --}}
                    <div>
                        <label
                            for="selling_price"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Selling Price
                        </label>

                        <input
                            id="selling_price"
                            type="number"
                            min="0"
                            step="0.01"
                            wire:model="selling_price"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('selling_price')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Alert quantity --}}
                    <div>
                        <label
                            for="alert_quantity"
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Low-Stock Alert Quantity
                        </label>

                        <input
                            id="alert_quantity"
                            type="number"
                            min="0"
                            step="0.001"
                            wire:model="alert_quantity"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                        >

                        @error('alert_quantity')
                            <p class="mt-1.5 text-sm text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
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
                        placeholder="Write product details"
                        class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                    ></textarea>

                    @error('description')
                        <p class="mt-1.5 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Image --}}
                <div>
                    <label
                        for="image"
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Product Image
                    </label>

                    <input
                        wire:key="product-image-{{ $imageInputKey }}"
                        id="image"
                        type="file"
                        wire:model="image"
                        accept=".jpg,.jpeg,.png,.webp"
                        class="block w-full rounded-lg border border-gray-300 bg-white text-sm text-gray-700 file:mr-4 file:border-0 file:bg-blue-50 file:px-4 file:py-2.5 file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >

                    <p class="mt-1 text-xs text-gray-500">
                        JPG, PNG or WEBP. Maximum size 2 MB.
                    </p>

                    <div
                        wire:loading
                        wire:target="image"
                        class="mt-2 text-sm text-blue-600"
                    >
                        Uploading image...
                    </div>

                    @error('image')
                        <p class="mt-1.5 text-sm text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                    {{-- New image preview --}}
                    @if ($image)
                        <div class="mt-4">
                            <img
                                src="{{ $image->temporaryUrl() }}"
                                alt="New product preview"
                                class="h-28 w-28 rounded-lg border border-gray-200 object-cover"
                            >

                            <button
                                type="button"
                                wire:click="clearNewImage"
                                class="mt-2 text-sm font-medium text-red-600 hover:underline"
                            >
                                Remove new image
                            </button>
                        </div>
                    @elseif ($existingImage)
                        {{-- Existing image preview --}}
                        <div class="mt-4">
                            <img
                                src="{{ Storage::disk('public')->url($existingImage) }}"
                                alt="Existing product image"
                                class="h-28 w-28 rounded-lg border border-gray-200 object-cover"
                            >

                            <button
                                type="button"
                                wire:click="removeCurrentImage"
                                class="mt-2 text-sm font-medium text-red-600 hover:underline"
                            >
                                Remove existing image
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Status --}}
                <label class="flex cursor-pointer items-center gap-3">
                    <input
                        type="checkbox"
                        wire:model="status"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >

                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Active product
                    </span>
                </label>

                {{-- Form buttons --}}
                <div class="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="save">
                            {{ $editingProductId
                                ? 'Update Product'
                                : 'Save Product' }}
                        </span>

                        <span wire:loading wire:target="save">
                            Saving...
                        </span>
                    </button>

                    @if ($editingProductId)
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

    {{-- Product list --}}
    <div
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="border-b border-gray-200 p-5 dark:border-gray-700">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Product List
                </h2>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Search and manage all products.
                </p>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search name, SKU or barcode..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                >

                <select
                    wire:model.live="categoryFilter"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:ring-blue-900"
                >
                    <option value="">All categories</option>

                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

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
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Category</th>
                        <th class="px-5 py-3">Brand</th>
                        <th class="px-5 py-3">Unit</th>
                        <th class="px-5 py-3">Purchase</th>
                        <th class="px-5 py-3">Selling</th>
                        <th class="px-5 py-3">Status</th>

                        @if (
                            auth()->user()->can('edit products') ||
                            auth()->user()->can('delete products')
                        )
                            <th class="px-5 py-3 text-right">
                                Actions
                            </th>
                        @endif
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($this->products as $product)
                        <tr
                            wire:key="product-{{ $product->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                        >
                            <td class="px-5 py-4">
                                <div class="flex min-w-56 items-center gap-3">
                                    @if ($product->image)
                                        <img
                                            src="{{ Storage::disk('public')->url($product->image) }}"
                                            alt="{{ $product->name }}"
                                            class="h-12 w-12 rounded-lg border border-gray-200 object-cover"
                                        >
                                    @else
                                        <div
                                            class="flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100 text-xs font-semibold text-gray-500 dark:bg-gray-800"
                                        >
                                            N/A
                                        </div>
                                    @endif

                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">
                                            {{ $product->name }}
                                        </p>

                                        <p class="mt-1 text-xs text-gray-500">
                                            SKU: {{ $product->sku }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                {{ $product->category->name }}
                            </td>

                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                {{ $product->brand?->name ?? 'No brand' }}
                            </td>

                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                {{ $product->unit->short_name }}
                            </td>

                            <td class="px-5 py-4 font-medium text-gray-700 dark:text-gray-300">
                                ৳{{ number_format((float) $product->purchase_price, 2) }}
                            </td>

                            <td class="px-5 py-4 font-medium text-gray-900 dark:text-white">
                                ৳{{ number_format((float) $product->selling_price, 2) }}
                            </td>

                            <td class="px-5 py-4">
                                @if ($product->status)
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
                                auth()->user()->can('edit products') ||
                                auth()->user()->can('delete products')
                            )
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        @can('edit products')
                                            <button
                                                type="button"
                                                wire:click="editProduct({{ $product->id }})"
                                                wire:loading.attr="disabled"
                                                class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-950 dark:text-amber-300"
                                            >
                                                Edit
                                            </button>
                                        @endcan

                                        @can('delete products')
                                            <button
                                                type="button"
                                                wire:click="deleteProduct({{ $product->id }})"
                                                wire:confirm="Are you sure you want to delete this product?"
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
                                    'edit products',
                                    'delete products',
                                ]) ? 8 : 7 }}"
                                class="px-5 py-12 text-center text-gray-500 dark:text-gray-400"
                            >
                                No products found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->products->hasPages())
            <div class="border-t border-gray-200 p-4 dark:border-gray-700">
                {{ $this->products->links() }}
            </div>
        @endif
    </div>
</div>